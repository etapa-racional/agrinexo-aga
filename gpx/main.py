#!/usr/bin/env python3
"""gpx/main.py - Gemini proxy for the AgriNexo assistant (Cloud Run).

Google stack used here:
  * Gemini 3.x (default gemini-3.6-flash) served by Vertex AI
  * Google GenAI SDK (google-genai), which builds the request and parses
    the reply through its own typed model rather than raw REST
  * Cloud Run, which hosts this service and whose service account is the
    only credential in the system able to reach Vertex AI

Scope, and why it is drawn here. api/assistant.php owns the agent loop:
the tool definitions, tool execution against the tenant PostgreSQL
database, tenant resolution and authorisation (AGNACNGSD.php),
conversation history, and the OpenAI-compatible providers. It holds
one provider-agnostic message shape internally and converts to a
provider's wire format only at the boundary. This service is that
boundary for Gemini, relocated across a network hop: it accepts the same
internal shape, performs one generate_content call, and returns the same
internal shape. Adding it therefore changed no control flow on the PHP
side and left the other two providers untouched.

Two properties follow from that scope and are intentional. The service is
stateless - it stores nothing, keeps no session, and opens no database
connection; the only data it observes is the prompt that would have gone
to Vertex AI in any case. And a conversation routed to a local Ollama
model never reaches this service at all.

Contract:
  POST /generate
    request  {request_id, model, system_prompt, messages[], tools[],
              json_only, response_schema?, temperature}
    response {content, reasoning,
              tool_calls[{id, name, arguments, thought_signature}]}
    error    {"error": {"message": ...}}, the shape assistant.php's
             extractProviderErrorMessage() reads
  GET /health
    {"ok": true} to anyone; the logging configuration actually in effect
    and the Vertex target only to a caller presenting X-Agent-Token
    (not /healthz: Google's front end reserves that path - see below)
"""

import base64
import json
import os
import time
import uuid

from flask import Flask, request
from google import genai
from google.genai import types

app = Flask(__name__)


VERTEX_PROJECT = os.environ.get("GOOGLE_CLOUD_PROJECT", "")
VERTEX_LOCATION = os.environ.get("GOOGLE_CLOUD_LOCATION", "global")

# Shared secret presented by PHP as X-Agent-Token; empty disables auth.
AGENT_TOKEN = os.environ.get("AI_AGENT_TOKEN", "")

# Payload logging; request metadata is always logged.
LOG_PAYLOADS = os.environ.get("AI_LOG_PAYLOADS", "off").strip().lower()

# Cloud Logging rejects entries above roughly 256KB.
LOG_MAX_CHARS = int(os.environ.get("AI_LOG_MAX_CHARS", "60000"))

_client = None


def get_client():
    """Constructed on first use rather than at import: a container that
    exits during module import surfaces in Cloud Run only as a generic
    startup failure, whereas a misconfigured project reported per request
    is diagnosable."""
    global _client
    if _client is None:
        _client = genai.Client(
            vertexai=True,
            project=VERTEX_PROJECT or None,
            location=VERTEX_LOCATION,
        )
    return _client




def emit(severity, message, **fields):
    entry = {"severity": severity, "message": message}
    entry.update({k: v for k, v in fields.items() if v is not None})
    print(json.dumps(entry, ensure_ascii=False, default=str), flush=True)


def payload_logging_enabled(req):
    if LOG_PAYLOADS == "on":
        return True
    if LOG_PAYLOADS == "on-request":
        return req.headers.get("X-Ai-Debug", "") == "1"
    return False


def clip(value):
    text = value if isinstance(value, str) else json.dumps(
        value, ensure_ascii=False, default=str
    )
    if len(text) <= LOG_MAX_CHARS:
        return text
    return text[:LOG_MAX_CHARS] + "...[truncated %d chars]" % (len(text) - LOG_MAX_CHARS)


def emit_payload(message, request_id, value):
    """Diagnostic logging must not be able to fail a request that would
    otherwise succeed: SDK response objects carry raw bytes (thought
    signatures) that defeat naive serialisation."""
    try:
        emit("DEBUG", message, request_id=request_id, payload=clip(value))
    except Exception as exc:  # noqa: BLE001 - deliberately swallowed
        emit("WARNING", message + ".unloggable", request_id=request_id, error=str(exc))




def normalize_schema(node):
    """Tool schemas arrive as JSON Schema with lowercase type names
    ('object', 'string'). The REST API accepts either case; types.Schema
    validates against an uppercase enum, so normalise before handing the
    schema to the SDK."""
    if not isinstance(node, dict):
        return node

    out = {}
    for key, value in node.items():
        if key == "type" and isinstance(value, str):
            out[key] = value.upper()
        elif key == "properties" and isinstance(value, dict):
            out[key] = {k: normalize_schema(v) for k, v in value.items()}
        elif key == "items":
            out[key] = normalize_schema(value)
        else:
            out[key] = value
    return out


def decode_tool_content(content):
    """Tool results cross the wire as JSON strings. An object or array is
    passed through as structured data; anything else is wrapped so the
    functionResponse payload is always structured."""
    try:
        decoded = json.loads(content)
    except (ValueError, TypeError):
        return {"result": content}
    return decoded if isinstance(decoded, (dict, list)) else {"result": content}


def build_contents(messages):
    contents = []
    index = 0
    count = len(messages)

    while index < count:
        message = messages[index] or {}
        role = message.get("role")

        # Gemini expects tool results as functionResponse parts, grouped.
        if role == "tool":
            parts = []
            while index < count and (messages[index] or {}).get("role") == "tool":
                tool_message = messages[index]
                parts.append(
                    types.Part(
                        function_response=types.FunctionResponse(
                            name=tool_message.get("name") or "",
                            response={
                                "result": decode_tool_content(
                                    tool_message.get("content") or ""
                                )
                            },
                        )
                    )
                )
                index += 1
            contents.append(types.Content(role="user", parts=parts))
            continue

        parts = []
        text = (message.get("content") or "").strip()
        if text:
            parts.append(types.Part(text=text))

        image = message.get("image")
        if image:
            parts.append(
                types.Part(
                    inline_data=types.Blob(
                        mime_type=image["mime_type"],
                        data=base64.b64decode(image["data"]),
                    )
                )
            )

        if role == "assistant":
            for tool_call in message.get("tool_calls") or []:
                # functionCall.args is a Struct and must serialise as JSON.
                part = types.Part(
                    function_call=types.FunctionCall(
                        name=tool_call.get("name") or "",
                        args=tool_call.get("arguments") or {},
                    )
                )
                # Gemini 3.x rejects a replay without the thought signature.
                signature = tool_call.get("thought_signature")
                if signature:
                    part.thought_signature = base64.b64decode(signature)
                parts.append(part)

        if parts:
            contents.append(
                types.Content(
                    role="model" if role == "assistant" else "user", parts=parts
                )
            )

        index += 1

    return contents


def build_tools(tool_definitions):
    if not tool_definitions:
        return None

    declarations = [
        types.FunctionDeclaration(
            name=tool["name"],
            description=tool.get("description", ""),
            parameters=normalize_schema(
                tool.get("input_schema") or {"type": "OBJECT", "properties": {}}
            ),
        )
        for tool in tool_definitions
    ]
    return [types.Tool(function_declarations=declarations)]




def normalize_response(response):
    candidates = getattr(response, "candidates", None) or []
    if not candidates:
        # An empty candidate list is usually a safety block.
        feedback = getattr(response, "prompt_feedback", None)
        reason = getattr(feedback, "block_reason", None) if feedback else None
        raise RuntimeError(
            "Gemini returned no candidates" + (" (blocked: %s)" % reason if reason else "")
        )

    parts = getattr(candidates[0].content, "parts", None) or []
    text_parts = []
    thought_parts = []
    tool_calls = []

    for position, part in enumerate(parts):
        if getattr(part, "text", None):
            # A thinking model may return a thought summary as a text part.
            if getattr(part, "thought", False):
                thought_parts.append(part.text)
            else:
                text_parts.append(part.text)

        function_call = getattr(part, "function_call", None)
        if function_call is not None and getattr(function_call, "name", None):
            signature = getattr(part, "thought_signature", None)
            tool_calls.append(
                {
                    "id": "gemini-tool-%d-%s" % (position, uuid.uuid4().hex[:13]),
                    "name": function_call.name,
                    "arguments": dict(function_call.args or {}),
                    "thought_signature": (
                        base64.b64encode(signature).decode("ascii") if signature else None
                    ),
                }
            )

    return {
        "content": "\n\n".join(text_parts).strip(),
        "reasoning": "\n\n".join(thought_parts).strip(),
        "tool_calls": tool_calls,
    }


def usage_fields(response):
    usage = getattr(response, "usage_metadata", None)
    if usage is None:
        return {}
    return {
        "prompt_tokens": getattr(usage, "prompt_token_count", None),
        "output_tokens": getattr(usage, "candidates_token_count", None),
        "thought_tokens": getattr(usage, "thoughts_token_count", None),
        "total_tokens": getattr(usage, "total_token_count", None),
    }




def error(message, status):
    return {"error": {"message": message}}, status


def token_ok(req):
    """True when the caller presented the shared secret. An unset AI_AGENT_TOKEN
    means the deployment is gated by Cloud Run IAM instead, so every request
    that arrives at all is already authenticated."""
    if not AGENT_TOKEN:
        return True
    return req.headers.get("X-Agent-Token", "") == AGENT_TOKEN


@app.get("/health")
def health():
    """Liveness probe, and the authoritative answer to which logging mode a
    revision is running - cheaper than inspecting revision environment
    variables, and it reports what the process actually read.

    Deliberately not /healthz. Google's front end reserves that path and
    answers it itself: on Cloud Run a request to /healthz never reaches the
    container, returning Google's own 404 page while every other path is
    routed normally. Verified against this service - /health, /status,
    /readyz and /livez all reach Flask, /healthz alone does not.

    The response is split by caller. Liveness is public, because platform
    probes carry no credential and the service is deployed
    --allow-unauthenticated. The diagnostic fields identify the project and
    region, so they require X-Agent-Token: none of them is a credential, but
    the project number makes the service's other run.app hostnames
    derivable, and a public URL should not hand that out."""
    if not token_ok(request):
        return {"ok": True}

    try:
        from importlib.metadata import version

        sdk_version = version("google-genai")
    except Exception:  # noqa: BLE001
        sdk_version = "unknown"

    return {
        "ok": True,
        "log_payloads": LOG_PAYLOADS,
        "log_max_chars": LOG_MAX_CHARS,
        "vertex_project": VERTEX_PROJECT,
        "vertex_location": VERTEX_LOCATION,
        "token_required": bool(AGENT_TOKEN),
        "google_genai_version": sdk_version,
    }


@app.post("/generate")
def generate():
    if not token_ok(request):
        emit("WARNING", "generate.unauthorized", remote_addr=request.remote_addr)
        return error("Unauthorized", 401)

    body = request.get_json(silent=True)
    if not isinstance(body, dict):
        return error("Request body must be a JSON object", 400)

    request_id = str(body.get("request_id") or uuid.uuid4().hex[:16])
    model = (body.get("model") or "").strip()
    if not model:
        return error("model is required", 400)

    messages = body.get("messages") or []
    if not isinstance(messages, list) or not messages:
        return error("messages must be a non-empty array", 400)

    tool_definitions = body.get("tools") or []
    json_only = bool(body.get("json_only"))
    debug = payload_logging_enabled(request)

    config_kwargs = {"temperature": float(body.get("temperature", 0.2))}

    system_prompt = (body.get("system_prompt") or "").strip()
    if system_prompt:
        config_kwargs["system_instruction"] = system_prompt

    tools = build_tools(tool_definitions)
    if tools:
        config_kwargs["tools"] = tools
        config_kwargs["tool_config"] = types.ToolConfig(
            function_calling_config=types.FunctionCallingConfig(mode="AUTO")
        )

    if json_only:
        config_kwargs["response_mime_type"] = "application/json"
        # The schema comes from the caller: it is FAO-56 domain knowledge.
        response_schema = body.get("response_schema")
        if response_schema:
            config_kwargs["response_schema"] = normalize_schema(response_schema)

    if debug:
        emit_payload("generate.request", request_id, body)

    started_at = time.time()
    try:
        contents = build_contents(messages)
        response = get_client().models.generate_content(
            model=model,
            contents=contents,
            config=types.GenerateContentConfig(**config_kwargs),
        )
        normalized = normalize_response(response)
    except Exception as exc:  # noqa: BLE001 - reported to the caller as 502
        emit(
            "ERROR",
            "generate.failed",
            request_id=request_id,
            model=model,
            latency_ms=int((time.time() - started_at) * 1000),
            error_type=type(exc).__name__,
            error=str(exc),
        )
        return error("%s: %s" % (type(exc).__name__, exc), 502)

    if debug:
        try:
            raw = response.model_dump(exclude_none=True)
        except Exception:  # noqa: BLE001
            raw = str(response)
        emit_payload("generate.response", request_id, raw)

    emit(
        "INFO",
        "generate.ok",
        request_id=request_id,
        model=model,
        latency_ms=int((time.time() - started_at) * 1000),
        message_count=len(messages),
        tool_count=len(tool_definitions),
        json_only=json_only,
        tool_calls=len(normalized["tool_calls"]),
        content_len=len(normalized["content"]),
        reasoning_len=len(normalized["reasoning"]),
        finish_reason=str(getattr(response.candidates[0], "finish_reason", "") or ""),
        payload_logging=debug,
        **usage_fields(response),
    )

    return normalized


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.environ.get("PORT", 8080)))
