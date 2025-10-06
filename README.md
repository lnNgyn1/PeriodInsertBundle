# PeriodInsertBundle

A plugin for Kimai that generates entries for a given time period.

## Installation

This plugin is compatible with the following Kimai releases:

| Bundle version | Minimum Kimai version |
|----------------|-----------------------|
| 1.8.0          | 2.38.0                |
| 1.7.0          | 2.36.0                |
| 1.3 - 1.6      | 2.26.0                |
| 1.0 - 1.2      | 2.1.0                 |

You can find the most notable changes between the versions in the file [CHANGELOG.md](CHANGELOG.md).

Download and extract the [compatible release](https://github.com/lnNgyn1/PeriodInsertBundle/releases) in `var/plugins/` (see [plugin docs](https://www.kimai.org/documentation/plugin-management.html)).

The file structure needs to look like this afterwards:

```bash
var/plugins/
├── PeriodInsertBundle
│   ├── PeriodInsertBundle.php
|   └ ... more files and directories follow here ... 
```

Then rebuild the cache:
```bash
bin/console kimai:reload --env=prod
```

## Permissions

This bundle comes with the following permission:

- `period_insert` - show the period insert screen to generate entries for a given time period

By default, it is assigned to each user with the role `ROLE_SUPER_ADMIN`.

Read how to assign these permissions to your user roles in the [permission documentation](https://www.kimai.org/documentation/permissions.html).

## Screenshot

![Alt text](/screenshot.png?raw=true "Period Insert plugin screenshot")

## Acknowledgements

This plugin is a migration of a bundle created by the software company MR Software GmbH. The plugin now supports Kimai 2.0!
