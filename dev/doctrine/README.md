# Engineering Doctrine

This directory contains the **Doctrine** — a collection of machine-readable charters that define *how we build, organize, secure, and ship software* across every layer of the stack.

These XML documents are not documentation in the traditional sense. They are **canonical policy** — intended to be read by both humans and AI assistants — that encode the rules, principles, and expectations governing this project.  

They serve three critical purposes:

1. **Authority:** They define how things *must* be done.  
2. **Continuity:** They allow development to resume instantly after a break, reboot, or team change.  
3. **Automation:** They allow AI agents to act deterministically instead of guessing intent.

---

## Philosophy

We believe that clarity, consistency, and constraint are strengths, not limitations.  
The Doctrine is how we enforce those strengths.

Each file is a *charter* — a focused set of non-negotiable principles for a specific domain. Together, they form the single source of truth for how we build, maintain, and ship software.

If a developer or agent is unsure how to proceed, the Doctrine is the first thing they should consult.

---

## How to Use These Files

### For Humans

- Treat these files as **living policy**. They are authoritative but can evolve — changes must be deliberate, reviewed, and documented.
- Before starting work, **read the relevant charters** for the domain you’re touching.
- If you encounter a situation where the Doctrine is ambiguous or incomplete, update it before proceeding.

### For AI Assistants

- **Always read the relevant XML files** before generating code, committing changes, or performing automated tasks.
- When referencing a charter, use specific sections or tags (e.g., `<security>`, `<behavioral_rules>`, `<workflow>`).
- If a file contains conflicting instructions, follow the more restrictive rule — never the more permissive one.

---

## Directory Structure

| File | Purpose |
|------|---------|
| **behavior.xml** | Universal behavioral expectations across all contexts. |
| **php.xml** | Language-level standards for PHP development. |
| **css.xml** | Front-end styling conventions, structure, and theming principles. |
| **database.xml** | Database usage, schema design, and query safety practices. |
| **code.xml** | Code structure, language separation, and modularity guidelines. |
| **security.xml** | Application-layer security practices (validation, authentication, authorization). |
| **git.xml** | Git workflow, branching strategy, commit standards, and deployment policy. |
| **version_control.xml** | Commit, staging, and branching safety checks (can be merged into git.xml over time). |
| **script.md** *(optional)* | Human-readable project state snapshot — allows resuming work after breaks. |

> **Note:** The Doctrine is intentionally modular. Load only the charters you need for a given task, or `cat` them together into a single instruction file when passing context to an AI.

---

## Principles That Guide the Doctrine

- **No ambiguity.** If two approaches are possible, the Doctrine must specify one.  
- **No “best practices.”** These are *required practices.*  
- **No orphaned code.** If a behavior, process, or convention isn’t encoded here, it’s either under review or not allowed.  
- **Everything deploys.** If it’s in the codebase and not in `.gitignore`, it will ship.  

---

## Updating the Doctrine

1. Open a feature branch specifically for Doctrine changes (e.g., `doctrine/update-php-guidelines`).
2. Update the relevant XML file(s) with clear, minimal changes.
3. Write a commit message explaining the reason and impact.
4. Merge only after review — Doctrine changes affect every developer and AI agent working on the project.

---

## Final Word

The Doctrine is our **engineering backbone**.  
It is how we encode discipline, avoid drift, and build software that is stable, maintainable, and predictable — no matter who is writing the code or how they’re writing it.

Treat it with the same respect you’d give production code.

