# Documentation Style Guide

The shared writing standards for all InteractiveTools libraries (voice,
vocabulary, page structure, code examples, method tables, renderer facts)
live in the team's
[internal docs repo](https://github.com/itools-internal/docs/tree/main/open-source)
under open-source/ (private, team access only). This file holds
SmartString-specific additions only.

- **"missing (null or \"\")"** - Many methods treat missing values
  specially, so the docs use this exact phrase everywhere missing is
  mentioned. Don't substitute "empty", "blank", or "falsy" - each variant
  reads like a different condition. Zero is never missing; rule lines say
  "zero counts as present".
