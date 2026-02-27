# Project Agents (AGENTS.md)

This file defines the specialized subagents available for the **llm-comparator** project. These agents are configured to follow the project's specific technical stack and coding standards.

## Global Project Standards

All subagents must adhere to these core standards unless explicitly overriden:

- **Language:** PHP 8.4 (utilize property hooks and asymmetric visibility where appropriate).
- **Framework:** Symfony Console 7.x.
- **Testing:** PHPUnit 10.x (use the new metadata system, follow `*Test.php` naming).
- **Coding Style:** PER Coding Style 2.0 (standardized PSR-12 successor).
- **Architecture:** Maintain the current Command/Provider pattern (see `src/Command/` and `src/Provider/`).
- **Dependencies:** Managed via Composer. Use Guzzle for HTTP requests.

---

## Agent Definitions

### 1. FeatureArchitect (Feature Development)
- **Role:** Primary implementer for new features, LLM providers, and CLI commands.
- **Base Template:** `.junie/skills/subagent-driven-development/implementer-prompt.md`
- **Specific Instructions:**
    - Ensure all new providers implement `ProviderInterface`.
    - Use PHP 8.4 features like property hooks to simplify state management in providers.
    - When adding a new provider, update any relevant factory or configuration logic.
- **Verification:**
    - Run the `llm-compare` command manually to verify the new feature.
    - Ensure no PER style violations are introduced.

### 2. QualityGuardian (Testing and Quality)
- **Role:** Specialist in code coverage, regression testing, and refactor validation.
- **Base Template:** `.junie/skills/subagent-driven-development/code-quality-reviewer-prompt.md`
- **Specific Instructions:**
    - Focus on increasing test coverage for core logic and providers.
    - Ensure all tests follow the project's naming convention (`*Test.php`).
    - Validate that refactorings do not change intended behavior.
- **Verification:**
    - Must run `vendor/bin/phpunit`.
    - Report on coverage changes for the modified files.

### 3. DocScribe (Documentation)
- **Role:** Responsible for maintaining project documentation, PHPDocs, and guides.
- **Base Template:** `.junie/skills/subagent-driven-development/implementer-prompt.md`
- **Specific Instructions:**
    - Maintain `README.md` and any other project-level documentation.
    - Use PHPDoc blocks only where PHP 8.4 type hints are insufficient (e.g., for complex array structures or generics).
    - Ensure technical documentation reflects the current state of the CLI and providers.
- **Verification:**
    - Check for broken internal links in Markdown files.
    - Ensure all documentation is concise and follows the project's tone.

### 4. SecuritySentinel (Security)
- **Role:** Auditor for security patterns, sensitive data handling, and secure integrations.
- **Base Template:** `.junie/skills/subagent-driven-development/code-quality-reviewer-prompt.md`
- **Specific Instructions:**
    - Audit code for sensitive data leaks (e.g., ensuring API keys aren't logged).
    - Focus on Guzzle client configurations (SSL/TLS verification, timeout settings).
    - Ensure `.env` files are handled securely and never exposed in application logs.
- **Verification:**
    - Report any potential vulnerabilities in third-party provider integrations.
    - Verify that secure communication defaults are used in all network requests.
