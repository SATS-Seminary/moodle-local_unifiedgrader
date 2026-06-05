# Behat tests for local_unifiedgrader

A small `@local_unifiedgrader_critical` smoke pack targeting the user-flow
regressions PHPUnit can't catch — the ones we've actually shipped fixes
for and want to lock in.

## Scope

These tests are deliberately narrow. The plugin already has 392 PHPUnit
tests covering managers, adapters, web services, hooks and privacy at
the server-call boundary. Behat covers what PHPUnit *can't*:

- Sequences of UI events (focus → input → focusout → autosave)
- Reactive-framework rendering after AJAX completes
- Cross-page persistence (preferences, dirty state)
- DOM affordances that look correct in isolation but interact badly
  (override badge, dash escape hatches, group filter)

If a regression you're worried about can be exercised by a single
`function test_*` then it belongs in PHPUnit, not here.

## What's covered

Each feature file targets a specific user flow we've shipped a fix for
and want a regression test against. Scenarios tagged `@local_unifiedgrader_wip`
are intentional stubs that document the intended check but require
either a new custom step or a generator helper before they can run —
they're flagged for follow-up rather than left as broken tests.

| File | Critical flow |
|---|---|
| `grade_override.feature` | Manual grade override survives subsequent rubric edits |
| `grade_reset.feature` | `-` clears grade, `--` clears grade + orphan submission, stray characters don't throw |
| `group_filter.feature` | Default group selection + per-cmid persistence across refreshes |
| `annotation_toolbar_after_zoom.feature` | Tool clicks still dispatch to the active annotation layer after a zoom — the v2.5.1 / v2.5.2 stuck-tool regression |

Worth adding next (not in this scaffold):

- Comment library pill scoping (system + current course only)
- Universal comment cross-course visibility
- Quiz post-grades dialog mentions only Marks / Max Marks / Overall feedback
- Override indicator + Reset to rubric total action

## Running locally

From the Moodle root:

```bash
# One-time: initialise the Behat environment
php admin/tool/phpunit/cli/init.php  # only needed for the parallel DBs
php admin/tool/behat/cli/init.php

# Run only this plugin's critical smoke pack
php admin/tool/behat/cli/run.php --tags='@local_unifiedgrader_critical&&~@local_unifiedgrader_wip'

# Run a single feature
php admin/tool/behat/cli/run.php tests/behat/grade_override.feature
```

The `~@local_unifiedgrader_wip` exclusion skips the WIP stubs so the
green run stays meaningful.

## Running in CI

`.github/workflows/ci.yml` already has a Behat step conditional on
`plugin/tests/behat` existing. Adding this directory turns it on — no
workflow edit needed. The CI step runs the entire plugin Behat suite
across the matrix (PHP 8.2 / 8.3 × MariaDB / Postgres).

If the Behat job's runtime becomes a bottleneck, narrow the CI to just
the critical tag:

```yaml
- name: Behat features
  run: moodle-plugin-ci behat --profile chrome --tags '@local_unifiedgrader_critical&&~@local_unifiedgrader_wip' ./plugin
```

## Custom step definitions

`behat_local_unifiedgrader.php` ships four plugin-specific steps:

- `I am on the Unified Grader for activity "<name>"` — resolves cmid by activity name
- `the marking panel has loaded` — waits for the reactive panel to settle
- `I enter "<value>" as the overall grade` — types into the grade input and triggers focusout
- `I set the rubric score for "<criterion>" to "<score>"` — fills a marking-guide score input by criterion name

Everything else uses core Moodle steps (`behat_general`, `behat_forms`,
`behat_navigation`, `behat_data_generators`). Prefer extending core
behaviour through scenarios before adding more step definitions here —
the maintenance burden is proportional to how custom you go.

## WIP scenarios — follow-up work

Three scenarios are stubbed out with `@local_unifiedgrader_wip`:

1. **Override survives rubric edit (grade_override.feature)** — needs
   a `Given a marking guide is attached to "X" with criteria:` step.
   The gradingform_guide API doesn't have a Behat generator out of the
   box; we'd need a custom step that calls
   `gradingform_guide_controller::update_definition()` with a definition
   built from the table data. ~30 lines.
2. **`--` removes orphan submission (grade_reset.feature)** — needs a
   step to seed an `assign_submission` row with `status='new'` plus an
   assertion step that the row is gone. Either custom generator or
   direct `$DB` write.
3. **Group filter persistence (group_filter.feature)** — needs a step
   that interacts with the multi-select group dropdown (`student_navigator.js`).
   Once the dropdown DOM is stable, `When I select group "X" in the
   navigator` is a thin wrapper around `behat_general::i_click_on`.

Pick these up when they become important enough to justify the step
definitions. Until then, the WIP tag keeps them in the file as
documentation of intent without contaminating the green run.
