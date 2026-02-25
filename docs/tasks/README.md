# Task System

This project uses a hybrid file-based task system:

- `board.md` for quick operational view,
- one markdown file per task under `items/`,
- shared template in `templates/task.template.md`,
- agent assignment map in `agents/roster.md`.

## Status Values

- `backlog`
- `ready`
- `in_progress`
- `blocked`
- `done`
- `cancelled`

## Workflow

1. Create or update task file first.
2. Sync `board.md` summary row.
3. Declare dependencies and acceptance criteria.
4. Assign owner (human or agent).
5. Move status only when criteria are verifiably met.

## Rules

- Task file is source of truth; board is summary.
- Every implementation task needs acceptance criteria.
- Every blocked task must include blocker reason and next action.
- Link related docs/PRDs/specs for context.
