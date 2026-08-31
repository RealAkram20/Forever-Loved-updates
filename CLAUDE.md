# Forever Loved

<!-- AIOS link. Added 2026-08-29. Keep this section at the top. -->

## This project is registered in the AIOS

Rio's operating system and long-term memory live at **`D:\OS`**. This project
is not an island: what is learned here is recorded there, and the standards
that govern the work are installed machine-wide.

**This project's knowledge base is `D:\OS\forever-os\`.** Classified **live,
actively developed** 2026-08-30. Status, reusable assets, provenance and open
questions are recorded there — do not re-derive what the wiki already holds.

⚠ **The reseller feature routes on the Host header.** It can be fully correct
in code and completely dead in production if DNS, TLS or `APP_URL` are wrong.
Any deployment, migration or domain change goes through
`RESELLER-PRODUCTION-CHECKLIST.md`. No exceptions.

**Before starting work, read:**

1. `D:\OS\forever-os\index.md`, then `D:\OS\references\codebase-map.md`
2. `~/.claude/skills/` — the standards that govern every project on this
   machine. `worklog` before your first edit, then
   `engineering-standards` / `wordpress` / `screen` / `ui-performance` as the
   work demands.

**Before finishing, use `self-improve`:**

| What you learned | Where it goes |
|---|---|
| A rule for all projects | `~/.claude/skills/` |
| A rule for this project only | this file, or `AGENTS.md` |
| An architectural decision | `docs/adr/` in this repo |
| A business decision | `D:\OS\decisions\log.md` |
| A fact about the client, money or status | `D:\OS\forever-os\` and `D:\OS\references\codebase-map.md` |
| Session state, gotchas, what you did NOT build | the worklog in this repo |

**The trigger is the second time.** First occurrence is an incident. Second is
a pattern, and a pattern belongs in a standard.


## The code graph

This repo carries a **Graphify** code graph at `graphify-out/` — local
tree-sitter AST, no LLM tokens, nothing leaves the machine.

```bash
graphify query "how does X work"   # traversal, token-budgeted
graphify explain "ClassName"       # a node and its neighbours
graphify affected "ClassName"      # what breaks if this changes
graphify god-nodes --top 10        # architectural hubs
graphify update . --no-cluster     # refresh after code changes (free, fast)
```

**Precedence.** The graph answers *structure* only. For status, client,
commercials, history and decisions, `D:\OS\references\codebase-map.md` still comes first, and this
repo's own docs outrank anything inferred from code. Never let a graph query
replace reading the wiki.

If `GRAPH_REPORT.md` says the graph was built from an older commit, run
`graphify update . --no-cluster` before trusting it. Setup notes and the two
known traps are in `D:\OS\references\code-graph.md`.
