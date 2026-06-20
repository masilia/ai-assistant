# Maintainability & Clean-Flow Review — 2026-06-17

**Scope:** `packages/masilia/ai-assistant/` (lib + bundle + frontend JS)
**Focus:** maintainability, code clarity, clean flow, and *removing*
over-engineering. This review prioritises **deleting** complexity over
adding it.

> **Basis of this review (read me first).** Every finding below is
> grounded in the **current source**, not in the older plan docs. Each
> claim cites the file and the verification (grep / read) that backs it.
>
> **Full-codebase pass (2026-06-17, 3rd pass):** the entire `src/` tree
> was read end-to-end (all agent tools, all provider adapters, the
> client/resolver layer, the prompt + field layer, and the full bundle
> layer: controllers, repositories, services, DI, listeners). This pass
> surfaced a **runtime bug** (§B1), dead repository methods, an image
> telemetry gap, and several tool-level inconsistencies — see the new
> “Critical bugs” section and §8–§12. It also **confirmed** that the
> deleted plan docs were stale: their open items (UndoLastTool Throwable
> catch, TrashRestorer dedup, JsonRequestDecoder trait, unified JSON 403
> permissions) are all already implemented.
>
> **Doc cleanup done (2026-06-17).** The previously-sprawling plan docs
> (the overlapping maintainability backlogs, the completed-feature
> design plans, and the empty placeholders) have been **deleted**. `docs/`
> now holds only the reference docs (`FEATURES`, `CONFIGURATION`,
> `USAGE`, `EXTENDING`) plus this review. §6 and §7 are therefore marked
> **resolved**.
>
> **Revision (2026-06-17, 2nd pass):** a deeper cross-layer re-analysis
> corrected the original §2. The PHP `/execute` path is *not* dead — it
> is referenced by the React widget — but the whole structured-plan flow
> is **unreachable end-to-end** because the backend never emits a
> `plan`. See the new §0 headline finding.

---

## Executive summary

The codebase is in genuinely good shape: the `lib`/`bundle` split is
clean, the adapter-registry pattern is consistent, value objects are
used well, and prompts are centralised. The biggest *current* risk is
**not missing structure — it is accumulated structure that is no longer
load-bearing**: an orphaned structured-plan flow, dead state fields, a
pass-through orchestrator, and ~15 overlapping planning documents.

The recommended direction is **subtract, then consolidate**.

| # | Theme | Severity | Effort |
|---|---|---|---|
| B1 | **Bug:** `loadOrLog()` is `private` but called cross-class | **High (correctness)** | Trivial |
| 0 | Frontend/backend agent protocol has diverged | **High (correctness)** | Medium |
| 1 | Dead state in `WizardState` | High (clarity) | Low |
| 2 | Orphaned structured-plan path (`/execute`, `AgentPlan`) | High (clean flow) | Medium |
| 3 | `AgentOrchestrator` is a pass-through | Medium | Low |
| 4 | `AgentRunner` is doing too much | Medium | Medium |
| 5 | Brittle intent/keyword heuristics | Medium | Medium |
| 6 | Documentation sprawl (over-engineering) | Medium | Low |
| 7 | Empty placeholder docs | Low | Low |
| 8 | Dead repository methods (4) | Medium (clarity) | Low |
| 9 | Image generation has no request-log telemetry | Medium | Medium |
| 10 | Tool convention drift (`FindParentCandidatesTool`) | Medium | Low |
| 11 | No transactional safety in multi-step structural tools | Medium (correctness) | Medium |
| 12 | Inconsistent permission model (`sudo()` in one tool) | Low/Medium | Low |

---

## Critical bugs

### B1. `FieldContextExtractor::loadOrLog()` is `private` but called cross-class — High

**Files:**
- `src/lib/FieldContextExtractor.php:98` — `private function loadOrLog(...)`
- `src/bundle/Controller/AiSuggestController.php:92` —
  `$this->contextExtractor->loadOrLog($contentId, 'for language list')`

`getLanguages()` calls `loadOrLog()` on the `FieldContextExtractor`
**instance from another class**, but the method is `private`. PHP only
allows private access from within the *declaring* class, so this throws
a fatal `Error: Call to private method FieldContextExtractor::loadOrLog()
from scope ...AiSuggestController` at runtime.

**Impact:** the `GET /admin/api/ai/languages/{contentId}` endpoint —
which populates the *"Translate from \<language\>"* dropdown in the
suggest modal — **crashes every time it is reached**. (It is wrapped in
no try/catch in `getLanguages()`, so the request 500s.)

**Verification:** grep shows `loadOrLog` is declared once (`private`)
and called from three places — two internal (`extractFromContent`,
`getFieldValueInLanguage`, `extractMatrixContextForRequest`, all fine)
and **one external** (the controller, not fine).

**Fix (trivial):** change `loadOrLog()` to `public` (it is already a
clean, side-effect-free load+log helper and the controller clearly
intends to reuse it). Add a regression test that calls the endpoint.

**Why no test caught it:** `phpunit.xml.dist` scopes coverage to
`src/lib/` only; the bundle controller layer is excluded, so no test
exercises this call site.

---

## 0. The agent's frontend and backend protocols have diverged — High

**This is the headline finding and was only visible by reading both
layers together.** The React widget and the PHP agent loop no longer
speak the same protocol, leaving two user-facing features silently dead.

**Evidence:**

- *Backend* `AgentRunner` (`src/lib/Agent/AgentRunner.php`) approves
  plans via **free text** — `propose_plan` returns a message
  `"...Shall I proceed? Say \"yes\"..."` and `isApprovalMessage()`
  parses the next user message. It **never** populates
  `AgentResponse::$plan`.
- `AgentResponse::withPlan()` (the only thing that sets `$plan`) is
  referenced **only by tests** (`tests/Agent/AgentResponseTest.php`) —
  never by production code. Verified by grep across `src/`.
- *Frontend* `AgentChatWidget.jsx` `handleSend()` keys its whole UX off
  `data.plan`: if present it renders `PlanDisplay` + a confirm button
  whose `handlePlanConfirm()` POSTs `plan.steps` to
  `AI_ROUTES.agentExecute` (`/admin/api/ai/agent/execute`).
- Because the backend never sends `plan`, **`PlanDisplay`,
  `handlePlanConfirm`, the `agentExecute` route, `AgentChatController::execute()`,
  `AgentOrchestrator::executePlan()` and `AgentPlan` are all unreachable
  in normal use.**
- Symmetrically, the backend *does* emit `options` for `ask_user`
  (`AgentResponse::$options`), but `handleSend()` **never reads
  `data.options`** and **never sends `selected_option`** — even though
  `AgentChatController::chat()` accepts it. So the option-button UX is
  dead on the frontend too.

**Net effect:** plan approval works only through a fragile English
"yes" string, and the structured `PlanDisplay`/option-button UI that the
code clearly invested in does nothing.

**Action — decide the intended protocol, then make exactly one real:**

- **Option A (recommended — finish the structured path).** Make the
  chat endpoint emit a structured `plan` (`{description, steps:[{tool,
  params}]}`) when `propose_plan` fires, and render the `ask_user`
  `options` as buttons that POST `selected_option`. This activates the
  UI that already exists and removes the brittle text parsing.
- **Option B (recommended — delete the structured path).** If text
  approval is the intended UX, **delete** `PlanDisplay.jsx`,
  `handlePlanConfirm`, `agentExecute`, `AgentChatController::execute()`,
  `AgentOrchestrator::executePlan()`, `AgentPlan`,
  `AgentResponse::withPlan()` and its tests. Then wire `ask_user`
  options/`selected_option` so at least that half is coherent.

Do **not** leave both half-built. Pick A or B; §2 and §3 below assume a
choice has been made.

---

## 1. Remove dead state from `WizardState` — High

**File:** `src/lib/Agent/Wizard/WizardState.php`

`WizardState` carries several fields and methods that are **written but
never read** (or never written at all):

- `$kind` and `$originalMessage` — never assigned anywhere in the flow.
- `$tools` — declared, never populated or consumed.
- `$facts` + `withFact()` + `withFacts()` — `matchAnswer()` in
  `AgentRunner` writes a `'selected'` fact, but **nothing ever reads
  `$facts` back**. It is a write-only scratchpad.

**Why it matters:** a new reader assumes these fields drive behaviour
(the docblock describes `facts` as the conversation memory), then
spends time discovering they are inert. This is the single
highest-confusion-per-line area in the agent subsystem.

**Action:**
- Delete `$kind`, `$originalMessage`, `$tools`.
- Delete `$facts`, `withFact()`, `withFacts()` **unless** you intend to
  actually feed facts back into the prompt. If you do want them, wire a
  read path (e.g. inject a "known facts" block into the system prompt)
  — otherwise remove.
- Update the class docblock to match the trimmed shape.

**Risk:** very low — removing unreferenced members.

---

## 2. Two divergent plan-execution code paths — High

Separate from §0's protocol issue, there are **two independent ways a
plan executes on the backend**, with different shapes and error policies:

1. **LLM-driven path (live)** — `propose_plan` tool call →
   `AgentRunner::handleProposePlan()` → user says "yes" →
   `AgentRunner::executeApprovedPlan()` → `mapIntentToTool()` → a
   **single** `$tool->execute()`.
2. **Step-list path (only reachable via the dead frontend button)** —
   `AgentChatController::execute()` builds an `AgentPlan` →
   `AgentOrchestrator::executePlan()` → **loops** over `$plan->steps`
   running each tool, stop-on-first-error.

Path 1 maps a single `intent` to one tool; path 2 runs an ordered list.
They share no execution primitive, so a fix to one (e.g. transactional
rollback, logging) silently misses the other.

**Why it matters:** "how does a plan actually run?" has two answers —
the definition of unclean flow.

**Action:** follows directly from the §0 decision.
- If §0 **Option B** (delete structured path): path 2 disappears
  entirely — delete `AgentPlan`, `executePlan()`, the `/execute` route.
- If §0 **Option A** (finish structured path): collapse both onto **one**
  execution primitive that takes an ordered `steps[]` list (path 1
  becomes a one-step list), with a single error policy.

**Already verified:** `agent/execute` *is* referenced by the frontend
(`api-routes.js` → `AgentChatWidget.jsx:103`), but that caller is itself
unreachable (§0). Do not delete blindly — delete the whole vertical
slice together.

---

## 3. Inline `AgentOrchestrator` or give it a reason to exist — Medium

**File:** `src/lib/Agent/AgentOrchestrator.php`

`chat()` is a pure pass-through to `AgentRunner::run()` (its only logic
is a null-user guard that arguably belongs in the controller). Its only
other method, `executePlan()`, belongs to the §0/§2 structured path. If
that path is deleted (§0 Option B), the orchestrator becomes a pure
wrapper around `AgentRunner`.

**Action:**
- If item 2 deletes `executePlan()`, **delete `AgentOrchestrator`** and
  have `AgentChatController` depend on `AgentRunner` directly. Move the
  null-user guard into the controller (where `resolveUserId()` already
  lives).
- If you keep both paths, that is fine — but then rename it to reflect
  that it is a *router* between two execution strategies, and document
  why both exist.

**Risk:** low — one DI rewire + one test update.

---

## 4. `AgentRunner` mixes four concerns — Medium

**File:** `src/lib/Agent/AgentRunner.php` (462 lines)

`AgentRunner` currently owns: turn-state transitions, the LLM loop,
tool-definition assembly (incl. two large inline JSON schemas),
the system prompt (inline heredoc), answer-matching heuristics, and
plan summarisation. That is four+ responsibilities in one class.

**Action (low-risk extractions, no behaviour change):**
- Move `buildToolDefinitions()` (the `ask_user` / `propose_plan` schemas)
  into a small `AgentControlTools` provider or constants file. The 80
  lines of inline schema dominate the class visually.
- Move `buildAgentSystemPrompt()` heredoc into a dedicated
  `AgentSystemPrompt` class/const (consistent with how `AiPromptBuilder`
  centralises the suggestion prompts).
- Group `isCancelMessage()` / `isApprovalMessage()` / `isNewRequest()` /
  `matchAnswer()` into an `AgentMessageClassifier` value/service.

This leaves `AgentRunner` as a readable state machine.

**Caution — avoid over-engineering:** do **not** introduce a formal
state-machine library or an event bus for this. Plain method extraction
into 2–3 collaborators is enough.

---

## 5. Replace brittle keyword/intent heuristics — Medium

**File:** `src/lib/Agent/AgentRunner.php`

- `isApprovalMessage()` and `isNewRequest()` rely on hard-coded English
  keyword lists (`'yes'`, `'create'`, `'build'`, ...). This silently
  breaks for non-English admins and is easy to fool ("yes please don't").
- `mapIntentToTool()` is a second, parallel place where intent strings
  are mapped to tools — separate from `ToolName` and the tool registry.

**Why it matters:** these heuristics are correctness-sensitive but live
as magic-string lists with no tests asserting the contract.

**Action:**
- Centralise the keyword lists as named constants and add a focused unit
  test fixing the expected matches (cheap regression guard).
- Consider letting the LLM emit an explicit `confirm: true/false`
  argument on `propose_plan` follow-ups rather than re-parsing free text.
  **Note:** the React side *does* already have a confirm button
  (`PlanDisplay`), it is just never shown (§0) — so wiring §0 Option A
  removes the need for `isApprovalMessage()` entirely.

---

## 6. Documentation sprawl — RESOLVED ✅

**Original problem:** `docs/` held ~15 markdown files including four
overlapping maintainability backlogs (`PLAN-code-quality-audit`,
`PLAN-agent-maintainability`, `PLAN-improvements`, root
`IMPROVEMENT_PLAN_3`) and eight completed-feature design plans, with no
clear source of truth.

**Done (2026-06-17):** all of those have been deleted. `docs/` now
contains only the four reference docs plus this review.

**Remaining follow-up (small):** `AGENTS.md` still linked some of the
deleted docs under "Docs worth reading before changes" / "Reference
docs"; those broken links have been pruned in the same pass. If a
living backlog is wanted later, keep it as a **single** `docs/BACKLOG.md`
rather than re-growing parallel plans.

---

## 7. Empty placeholder docs — RESOLVED ✅

`docs/ARCHITECTURE.md` and `docs/IMPROVEMENT_PLAN_V2.md` were 0-byte
placeholders; both have been deleted. If an architecture doc is wanted
later, the two-layer split + agent-loop diagram already in `AGENTS.md`
is most of the content.

---

## 8. Dead repository methods — Medium (clarity)

**File:** `src/bundle/Repository/AiProviderRepository.php`

Four query methods are defined but **never called** anywhere in `src/`
(verified by grep):

- `findActiveEntity()`
- `findActiveEntityForSiteaccess()`
- `findBySiteaccess()`
- `findAllWithSiteaccess()`

The live methods are `findActiveForSiteaccess()` + `findActiveImageTarget()`
(used by the resolvers) and `findActive()` (used by `HealthChecker`). The
admin controller uses the inherited `findAll()`, **not** `findActiveEntity()`.

This also makes `AGENTS.md`'s "Runtime wiring gotchas" note stale — it
claims the admin controller uses `findActiveEntity()/findActiveEntityForSiteaccess()`
for raw entities, which is no longer true.

**Action:** delete the four unused methods (and trim the matching
interface entries in `AiProviderRepositoryInterface` if present), or wire
them if a planned feature needs them. Update the `AGENTS.md` note.

---

## 9. Image generation has no request-log telemetry — Medium

**Files:** `src/lib/Client/ImageGenerationClient.php` vs
`src/lib/Client/AiClient.php` + `src/bundle/Service/DoctrineRequestLogger.php`

Text/chat calls route through `AiClient`, which records **every** call
(success/failure, latency, tokens, provider, siteaccess) via
`RequestLoggerInterface` into `app_ai_request_log` — the data behind the
Usage tab. `ImageGenerationClient` does its **own** HTTP + logging and
**never touches `RequestLoggerInterface`**, so image generation is
completely invisible to the Usage/telemetry tab and to error analytics.

**Why it matters:** image calls are typically the *most expensive* per
request; leaving them out of telemetry undercuts the whole Usage feature
and is a surprising inconsistency for anyone extending it.

**Action:** inject `RequestLoggerInterface` into `ImageGenerationClient`
and log a row per generation (it already has provider/model/latency in
scope), mirroring `AiClient::logSuccess()/logFailure()`. Consider a
shared small `RequestTelemetry` helper so the two clients log identically.

**Secondary (minor):** `isConfigured()` and `generate()` each call
`targetResolver->resolve()`, and tools call `isConfigured()` *then*
`generate()`, so `resolve()` runs 2× per image (and N+1× in
`BlockImagePreGenerator`, once per field). Cache the resolved target for
the duration of a request if `resolve()` hits the DB.

---

## 10. Tool convention drift — `FindParentCandidatesTool` — Medium

**File:** `src/lib/Agent/Tool/Content/FindParentCandidatesTool.php`

This tool breaks three conventions every other tool follows:

- **Hardcoded name.** `getName()` returns the literal
  `'find_parent_candidates'` instead of a `ToolName` constant. It is the
  only tool not registered in `ToolName` (which `AGENTS.md` calls "the
  centralised tool identifiers"). Silent-drift risk.
- **Non-standard error handling.** Its `catch` logs at `warning` and
  returns `ToolResult::error($e->getMessage())` — **leaking the raw
  exception message to the LLM/user**. Every other tool uses
  `AgentErrorHelper::handle()`, which maps exceptions to safe messages.
- **Pointless branch.** The `if ($siteaccess !== '') { searchSiteaccess($siteaccess...) }
  else { searchSiteaccess('' ...) }` both call the same method with the
  same effect (the empty case is handled inside `searchSiteaccess`).

**Action:** add `ToolName::FIND_PARENT_CANDIDATES`, switch the catch to
`AgentErrorHelper::handle()`, and collapse the if/else to a single call.

**Minor sibling issues:** unused `$e` in `SearchContentTool` and
`UndoLastTool` catch clauses; `ToolName`'s docblock says identifiers are
used by "AgentOrchestrator dispatch" but the dispatch actually lives in
`AgentRunner::mapIntentToTool()`.

---

## 11. No transactional safety in multi-step structural tools — Medium (correctness)

**Files:** `src/lib/Agent/Tool/Structural/CreatePageStructureTool.php`,
`CreateSiteStructureTool.php`

Both tools publish many content items in sequence (page → folder →
blocks → items → relation updates; or site → layout → home → media
folders → pages). Each `createAndPublish*()` commits immediately. There
is **no transaction wrapping**, so a failure on step *N* leaves steps
*1..N-1* already published — a half-created page/site the user must
clean up by hand. The `catch` only logs and returns an error message; it
does not roll back.

**Action:** wrap each tool's `execute()` body in
`$repository->beginTransaction()` / `commit()` / `rollback()` (Ibexa
supports this) so a mid-sequence failure leaves no partial tree. This is
the highest-value robustness fix in the agent write-path.

**Related DRY note (low priority):** `CreatePageStructureTool`,
`CreateSiteStructureTool` and `BlockImagePreGenerator` each re-implement
`findRelationField()` (first `ezobjectrelationlist` field), and the two
structural tools both have private `createFolder()` / `resolveConfig()`
helpers. A small shared `StructuralContentHelper` (or trait) would remove
the triplication — but keep it minimal; do **not** build a generic
"structure engine".

---

## 12. Inconsistent permission model across write-tools — Low/Medium

**Files:** `src/lib/Agent/Tool/Content/GenerateImageTool.php` (uses
`$repository->sudo(...)`) vs `CreateContentTool`, `UpdateContentTool`,
`TrashContentTool` (run as the current user).

`GenerateImageTool` wraps its content update in `repository->sudo()`,
**bypassing permission checks**, while the other write-tools rely on the
current user's permissions (and surface `UnauthorizedException` via
`AgentErrorHelper::unauthorized()`). So the same agent can be blocked
from updating a field directly but succeed in writing an image to it.

**Action:** decide one policy. Either all agent write-tools run as the
current user (drop the `sudo()` — preferred, since the agent is admin-only
behind `setup/administrate`), or document explicitly why image writes
need elevated rights. Don't leave it implicit.

---

## Anti-goals (do **not** do these)

To keep the "prevent over-engineering" objective front-and-centre:

- **No** new abstraction layers around adapters — the
  `ProviderAdapterInterface` + registry pattern is already the right
  amount of structure. Resist a "provider factory factory".
- **No** generic plugin/event system for tools — the tagged-iterator
  `ToolRegistry` is sufficient.
- **No** introducing a workflow/state-machine framework for the agent
  loop (see §4).
- **No** re-growing plan-doc sprawl — if a backlog is needed, keep a
  single `docs/BACKLOG.md` (§6).

---

## Suggested order of execution

1. **§B1** (make `loadOrLog()` public) — one-word fix for a live 500;
   ship immediately, add a controller-level regression test.
2. **§1 + §8** (delete dead `WizardState` state + dead repo methods) —
   safe, pure subtraction, biggest clarity-per-effort.
3. **§6 + §7** (doc consolidation / obsolete docs) — already largely
   done; just keep the single `AGENTS.md` pointer accurate.
4. **§11** (transaction-wrap the structural tools) — highest-value
   correctness fix in the write-path.
5. **§10 + §12** (tool convention drift + permission model) — small,
   mechanical consistency fixes.
6. **§9** (image-generation telemetry) — wire `RequestLoggerInterface`.
7. **§0** (decide Option A vs B for the agent protocol) — product
   decision that unblocks §2, §3, §5.
8. **§2 → §3 → §4 → §5** (collapse plan path, inline orchestrator,
   extract `AgentRunner` collaborators, harden classifier).

Behaviour-preserving except **§0/§2** (deliberate UX change) and **§12**
(if `sudo()` removal changes who can generate images).
