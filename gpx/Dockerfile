FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py ./

# Threads rather than processes: a request spends nearly all its time blocked
# on Vertex AI, so concurrency costs almost no CPU or memory. The worker
# timeout matches assistant.php's set_time_limit(300), ensuring the proxy
# never terminates a request before the caller has stopped waiting for it.
ENV PORT=8080
CMD exec gunicorn --bind :$PORT --workers 1 --threads 8 --timeout 300 main:app
