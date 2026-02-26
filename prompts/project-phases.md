Plan this project into phases and what needs to be built. I want to have a complete task list that needs to happen, based on @docs/user-stories.md, @docs/project-description.md and @docs/database-schema.md.

For each task, specify the automated feature tests to be generated, as acceptance criteria.

For phases and sub-phases, use numeration like "Phase 1" or "Phase 5.3" so they could be referenced by numbers, when later given to AI agent to implement.

Check in the code, which tasks are already completed, and mark them accordingly [x]. Put the result into @docs/project-phases.md.

Technical Detail
There needs to be a phase for creating frontend configurations, as well as creating colors and basic components such as buttons, inputs, selects, checkboxes, radio buttons, and modals. see docs/design
Layout base for logged-in and non-logged-in users.
The kanban construction phase and the drag-and-drop functionality must specifically use Livewire 4’s wire:sort, which was created specifically to implement drag-and-drop features without the need for additional packages.