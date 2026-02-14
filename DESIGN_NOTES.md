# Unified Grader - Design Notes

## Deferred: Student Feedback Viewer & COI Framework

**Status**: Pinned for post-grader consideration

### The Question
How should students view feedback? Options:
1. Push feedback into each activity's native space (fragmented but familiar)
2. Build a unified feedback viewer (coherent but new)
3. Hybrid: push grades natively, but offer a unified feedback dashboard

### COI Framework Considerations
The Community of Inquiry model (Garrison, Anderson, Archer) identifies three
interdependent presences in meaningful educational experiences:

- **Teaching Presence** (design, facilitation, direct instruction):
  The grader directly supports this. A feedback viewer could make teaching
  presence more visible and intentional by consolidating all instructor
  feedback in one place, showing students the full arc of guidance across
  activities.

- **Social Presence** (projecting oneself as a real person):
  One-way feedback is weak on social presence. A feedback viewer that enables
  dialogue (student replies to feedback, follow-up comments, clarification
  requests) would strengthen this. Consider: should feedback be a conversation
  rather than a monologue?

- **Cognitive Presence** (constructing meaning through reflection and discourse):
  Consolidated feedback across assignments, forums, and quizzes could help
  students see patterns in their learning. A unified view supports metacognition
  - "I keep getting the same feedback on critical analysis across all activities."

### Architecture Implications
- The grader stores annotations, comments, and notes in plugin-owned tables
- This gives us the data layer for either approach (native push, unified viewer, or both)
- Grade values should always flow back to the gradebook via native APIs
- Rich feedback (annotations, audio/video, threaded comments) can live in our tables
- Decision on student-facing experience can be deferred without architectural debt

### Further Reading
- Garrison, D.R., Anderson, T., & Archer, W. (2000). Critical inquiry in a
  text-based environment: Computer conferencing in higher education.
- Vaughan, N., Cleveland-Innes, M., & Garrison, D.R. (2013). Teaching in
  Blended Learning Environments: Creating and Sustaining Communities of Inquiry.
