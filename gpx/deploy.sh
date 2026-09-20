#!/usr/bin/env bash
# DEFERRED: AI_AGENT_TOKEN is a plain env var, not Secret Manager.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

: "${PROJECT_ID:?set PROJECT_ID}"
: "${AGENT_TOKEN:?set AGENT_TOKEN (a long random string, e.g. openssl rand -hex 32)}"

REGION="${REGION:-europe-west1}"
SERVICE="${SERVICE:-agrinexo-gemini-proxy}"
LOG_PAYLOADS="${LOG_PAYLOADS:-off}"
SA_NAME="agrinexo-gemini-proxy"

gcloud services enable \
  run.googleapis.com \
  cloudbuild.googleapis.com \
  artifactregistry.googleapis.com \
  aiplatform.googleapis.com \
  --project "$PROJECT_ID"

SA="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"
if ! gcloud iam service-accounts describe "$SA" --project "$PROJECT_ID" >/dev/null 2>&1; then
  gcloud iam service-accounts create "$SA_NAME" \
    --display-name "AgriNexo Gemini Proxy" \
    --project "$PROJECT_ID"
fi

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member "serviceAccount:${SA}" \
  --role roles/aiplatform.user \
  --condition=None >/dev/null

if ! gcloud artifacts repositories describe cloud-run-source-deploy \
     --location "$REGION" --project "$PROJECT_ID" >/dev/null 2>&1; then
  gcloud artifacts repositories create cloud-run-source-deploy \
    --repository-format docker \
    --location "$REGION" \
    --description "Cloud Run source deployments" \
    --project "$PROJECT_ID"
fi

gcloud run deploy "$SERVICE" \
  --source . \
  --project "$PROJECT_ID" \
  --region "$REGION" \
  --service-account "$SA" \
  --allow-unauthenticated \
  --timeout 300 \
  --memory 512Mi \
  --max-instances 4 \
  --set-env-vars "GOOGLE_CLOUD_PROJECT=${PROJECT_ID},GOOGLE_CLOUD_LOCATION=global,AI_AGENT_TOKEN=${AGENT_TOKEN},AI_LOG_PAYLOADS=${LOG_PAYLOADS}"

URL="$(gcloud run services describe "$SERVICE" --project "$PROJECT_ID" --region "$REGION" \
  --format='value(metadata.annotations["run.googleapis.com/urls"])' \
  | python3 -c 'import json, sys
raw = sys.stdin.read().strip()
urls = json.loads(raw) if raw.startswith("[") else []
print(next((u for u in urls if not u.endswith(".a.run.app")), urls[0] if urls else ""))')"

if [ -z "$URL" ]; then
  URL="$(gcloud run services describe "$SERVICE" --project "$PROJECT_ID" --region "$REGION" --format 'value(status.url)')"
fi

cat <<EOF

Deployed: $URL

Set on the PHP host (Apache environment, or the vhost / php-fpm pool):
  AGRINEXO_AI_AGENT_URL=$URL
  AGRINEXO_AI_AGENT_TOKEN=$AGENT_TOKEN

Verify liveness (public):
  curl -s $URL/health | python3 -m json.tool

Verify configuration, including which logging mode the revision is running
(the diagnostic fields require the token):
  curl -s -H "X-Agent-Token: \$AGENT_TOKEN" $URL/health | python3 -m json.tool
EOF
