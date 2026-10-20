# Security policy

## Supported versions

Only the latest release of Vitalog receives security fixes.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately through GitHub:
<https://github.com/odolbeau/nextcloud-vitalog/security/advisories/new>

Include the Nextcloud and Vitalog versions, the steps to reproduce, and the impact you see. You will get an answer as soon as possible,
and a fix and a coordinated disclosure once the report is confirmed.

Vitalog stores medical information: every query is scoped to the signed-in user, and a report about data leaking between users is
treated as critical.
