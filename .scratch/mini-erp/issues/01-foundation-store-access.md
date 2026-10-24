# 01: Foundation: one store per user, phone login, roles, employees, super-admin

**What to build:** A visitor signs up with name, phone, password and store name and becomes the owner of a new store that already has the warehouse _Asosiy ombor_. Everyone logs in with phone + password. The owner creates employees, resets their passwords and deactivates them. The sidebar shows only what the user's permissions allow, and forbidden URLs are refused. A super-admin, created only from the command line, lists stores, blocks/unblocks them and resets owner passwords, without access to any store's business data. The UI shell is in Uzbek Latin. Spec: `.scratch/mini-erp/spec.md` stories 1–16; decisions Q1, Q2, Q15–Q17, Q29–Q31, Q42–Q46, Q53, U1, U2; ADR-0003.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [x] Personal teams, team switching, create-team, invitations and the team-slug URL prefix are removed; the current store is resolved from the user's single membership. Ask the user before deleting starter-kit tests that cover removed features.
- [x] spatie/laravel-permission installed in teams mode (store = team); seeded roles `owner`, `employee` (per store) and global `super-admin`; the full permission list from the spec's "Tenancy, identity and permissions" is seeded.
- [x] Every business query is scoped to the current store by a reusable mechanism that later tickets apply to their tables; a test proves a user of store A cannot read or change store B's data.
- [x] Sign-up (name, phone, password, store name, no email) creates user + store + owner role + _Asosiy ombor_; phone normalised to `+998XXXXXXXXX`, unique, masked input `+998 __ ___ __ __`; duplicate phone shows an Uzbek error.
- [x] Login by phone + password; email login, email verification and email password reset removed; 2FA and passkeys still work.
- [x] Owner can create an employee (name, phone, password), reset an employee's password and deactivate/reactivate an employee; deactivated users cannot log in.
- [x] Sidebar is one shared menu built from the user's permissions (groups from the spec); employees get 403 on owner-only routes.
- [x] `app:create-super-admin` creates a super-admin with no store; super-admin area: store list (name, owner, phone, status), block/unblock, reset owner password; super-admin gets 403 on store business routes.
- [x] A blocked store's users are logged out on their next request and see "Do'kon bloklangan" at login; unblocking restores access with data intact.
- [x] App locale `uz`; shell, auth pages and validation messages in Uzbek Latin.
- [x] HTTP feature tests cover sign-up, phone login, deactivated user, blocked store, employee management, role 403s, super-admin isolation; command test for `app:create-super-admin`.
