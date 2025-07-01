# Version 1.7.0

Compatible with Kimai version 2.36.0 or higher.

- Updated to make compatible with Kimai version 2.36.0

# Version 1.6.0

Compatible with Kimai version 2.26.0 or higher.

- Added meta fields to address [#18](https://github.com/lnNgyn1/PeriodInsertBundle/issues/18)
- Added change log

# Version 1.5.0

Compatible with Kimai version 2.26.0 or higher.

- Added internal rate field to address [#16](https://github.com/lnNgyn1/PeriodInsertBundle/issues/16)
- Added `PeriodInsertPreCreateForm` to allow values (user, project, activity, description, tags, select days) to be preset by URL
- Added options to system configuration menu to allow creating time entries on absences and non-work days
- Miscellaneous formatting and code style changes

# Version 1.4.0

Compatible with Kimai version 2.26.0 or higher.

- Added period insert validator
  - Performs similar checks to timesheet validator
- Refactored controller, entity, form, and repository code
  - Made form a child class of `TimesheetEditForm`
  - Added event listeners to modify period insert entity after submitting
  - Removed redundant functions, statements, and loops
  - Adjusted access modifiers
- Renamed and documented variables, fields, and functions
- Corrected `include_user` form permission
- Updated title

# Version 1.3.0

Compatible with Kimai version 2.26.0 or higher.

- Added work day and absences check to timesheet entry to address [#9](https://github.com/lnNgyn1/PeriodInsertBundle/issues/9)
- Removed punch or time-clock tracking mode check to resolve [#10](https://github.com/lnNgyn1/PeriodInsertBundle/issues/10)

# Version 1.2.0

Compatible with Kimai version 2.1.0 or higher.

### Changes
 - Applied time tracking settings to period insert
   - Allow overlapping time entries option to address [#4](https://github.com/lnNgyn1/PeriodInsertBundle/issues/4)
   - Allow time entries in the future option
   - Allow time entries with an empty duration option
   - Allow overbooking of stored budgets option
   - Time tracking modes: default, duration, and time-clock
 - Applied automatic detection of billable field
 - New checks and error messages

### Minor fixes
- Changed help page link to the [Kimai store page](https://www.kimai.org/store/lnngyn-period-insert-bundle.html)
- Removed nonzero duration requirement
- Handled entries in which duration is longer than 24 hours
- Renamed labels and refactored code to match Kimai 2.22.0
- Documented field types

### A detailed writeup can be found [here](https://github.com/lnNgyn1/PeriodInsertBundle/issues/4#issuecomment-2384355872).

# Version 1.1.0

Compatible with Kimai version 2.1.0 or higher.

### Changes
- Added selection of specific days and begin time field to address [#1](https://github.com/lnNgyn1/PeriodInsertBundle/issues/1). By default, they are selected.

### Minor fixes
- Fixed visibility of tag item on form
- Changed link to help page
- Removed placeholder for hourly rate
- Required duration field
- Route back to timesheet page

# Version 1.0.0

Initial release. Compatible with Kimai version 2.1.0 or higher.