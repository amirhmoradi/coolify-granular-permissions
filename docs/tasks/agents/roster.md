# Agent Roster (Planning Phase)

## Agent Domains

- `architecture-agent`: exporter architecture and profile schema decomposition.
- `dependency-agent`: dependency graph model for entity requirements.
- `security-agent`: secrets isolation, vault boundaries, and risk controls.
- `ops-agent`: drift detection, rollback policy.

## Assignment Rules

- One owner per task at a time.
- Tasks with shared dependencies require explicit coordination notes.
- Planning-only mode: agents produce specs/tasks, no code changes.

## Output Requirements

Each planning agent output must include:

- proposed task breakdown,
- dependencies and risks,
- acceptance criteria updates,
- file paths impacted.
