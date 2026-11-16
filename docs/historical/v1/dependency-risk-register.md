# Dependency risk register

Date: 14 October 2026

## Current audit result

- `npm audit --omit=dev`: 0 vulnerabilities.
- Full `npm audit`: 8 high-severity advisories, all in development/build dependency paths.
- The earlier `fflate` moderate advisory is resolved by the compatible `0.8.3` override.
- No `npm audit fix --force` was used because the proposed changes are breaking downgrades that conflict with the current Next/Vinext toolchain.

## Accepted development-only risks pending upstream fixes

| Dependency path | Exposure | Present mitigation | Disposition |
|---|---|---|---|
| `eslint-config-next` → `@next/eslint-plugin-next` → `fast-glob` → `micromatch` → `braces` | Crafted glob patterns can cause excessive recursion while lint tooling executes | Lint runs only against trusted repository patterns/source; it is absent from the production dependency audit and Worker runtime | Conditional risk acceptance; monitor upstream and update when a non-breaking fix exists |
| `vinext` → `vite-plugin-commonjs` → `vite-plugin-dynamic-import` → `fast-glob` → `micromatch` → `braces` | Same glob-processing issue during trusted builds/deployments | Builds accept only reviewed repository source; deployment credentials are short-lived; no untrusted user input is passed into build globs | Conditional risk acceptance; monitor Vinext/upstream and remove once fixed |

The current published `braces` release remains within the advisory range. Audit's suggested fixes downgrade `eslint-config-next` or Vinext across major/incompatible versions and are therefore not defensible automatic remediations. Because build tools execute code, this is not marked “no risk”: clean build agents, reviewed lockfile changes, restricted deployment credentials and trusted source inputs remain mandatory.

Production approval still requires the risk owner to accept these development-only paths or an upstream-compatible upgrade to remove them.

