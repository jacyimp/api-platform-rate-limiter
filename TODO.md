# TODO

## 1. Make Behat meaningful

Behat currently exists in the project but should prove actual consumer-facing behavior.

Add feature scenarios for:

* operation-specific rate limits;
* shared buckets;
* combined limits;
* rate-limit rejection;
* separate identities;
* custom identity resolvers;
* custom bypass rules;
* dynamic `RateLimitProviderInterface`;
* explicitly tagged/manual DI services;
* per-limit identity resolution;
* per-limit bypass behavior.

Prefer scenarios that exercise the package through a real Symfony kernel.

Do not duplicate low-level unit tests in Gherkin.

Use PHPUnit for implementation edge cases and Behat for externally observable package behavior.

---

## 2. Add Infection with 100% MSI

Add Infection mutation testing.

Target:

```text
src/
```

Require:

```text
MSI >= 100%
Covered MSI >= 100%
```

Add a Composer command such as:

```text
composer mutation
```

Run Infection in a dedicated CI quality job.

Do not run Infection on every supported PHP version.

Avoid broad ignored-mutator lists or directory exclusions.

If a genuinely equivalent mutant cannot be killed:

* document why;
* exclude it narrowly;
* keep such exceptions rare.

The goal is meaningful mutation resistance, not a cosmetic score.

---

## 3. Documentation hardening

Update README after the public API stabilizes.

Document:

* simplest operation-specific usage first;
* shared buckets;
* combined limits;
* identity resolution;
* bypass behavior;
* per-limit resolvers and bypasses;
* dynamic rate-limit providers;
* manual service tags;
* public events;
* exception hierarchy;
* multi-instance cache requirements;
* sequential multi-limit consumption behavior.

Keep advanced customization below the basic quick-start.

Do not document internal classes as supported extension points.
