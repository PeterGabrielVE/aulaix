"""AulaX AI service — architectural skeleton.

This service is intentionally decoupled from the Laravel monolith: it is a
separate deployable unit with its own container, reachable only over HTTP.
No AI features are implemented yet (none are in Fase 1 of the backlog); this
just proves out the wiring (Laravel -> HTTP -> this service) that future AI
features (grading assistance, content generation, etc.) will build on.
"""

from fastapi import FastAPI

app = FastAPI(title="AulaX AI Service", version="0.1.0")


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "service": "aulaix-ai-service"}
