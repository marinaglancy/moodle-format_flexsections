# Security policy

## Supported versions

| Branch | Moodle versions | Status |
| --- | --- | --- |
| `MOODLE_500_STABLE` | 5.0 - 5.2 | Maintained, receives security fixes |
| `MOODLE_401_STABLE` | 4.1 - 4.5 | Maintained, receives security fixes |
| Older branches | 4.0 and earlier | Not maintained |

Security fixes are released as new tagged versions on the maintained branches. If you run an
older version, please upgrade to the latest release for your Moodle version.

## Reporting a vulnerability

Please do not report security issues in public GitHub issues or pull requests, in the Moodle
forums, or in the plugin's entry in the Moodle plugins directory. Public reports let attackers
act before a fix is available to sites.

Instead, report privately through GitHub:

https://github.com/marinaglancy/moodle-format_flexsections/security/advisories/new

Helpful things to include:

- the plugin version and branch, and the Moodle version you are running;
- steps to reproduce, and which role (guest, student, teacher, manager) is needed;
- what the issue allows an attacker to do;
- a proof of concept, if you have one.

You should receive an acknowledgement within a few days. If you have had no reply after a week,
please open a public issue asking to be contacted, without describing the vulnerability.

## Disclosure process

Reports are handled with coordinated disclosure:

1. The report is confirmed privately and an embargo is agreed. The default is 90 days and it is
   negotiable, for example when a fix is ready sooner.
2. The fix is released as a new tagged version on every maintained branch that is affected.
3. A GitHub security advisory is published and the fix is noted in `CHANGELOG.md`.

Reporters are credited in the advisory unless they ask not to be.

## Scope

This policy covers the code in this repository. If the issue is in Moodle itself or in another
plugin, please report it to the relevant maintainers instead. Moodle core issues go to
https://moodle.org/security/report, as described at
https://moodledev.io/general/development/process/security.
