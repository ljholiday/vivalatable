```markdown
# VivalaTable Main Control Manifest

> **Purpose:**  
> Central operational document for VivalaTable.  
> Acts as a live index, doctrine reference, and executable checklist for developers and automation scripts.  
> Every directive here is derived from the XML charters in `/dev/`.

---

## 1. System Overview

VivalaTable is a standalone PHP 8.1+ MVC web application for community, event, and conversation management—built on the principles of **Circles of Trust** and **anti-algorithm social filtering**.

### Runtime stack
| Layer | Technology | Notes |
|-------|-------------|-------|
| Web | Apache (mod_rewrite) / Nginx | See `env.xml` |
| Server | PHP 8.1+ (strict typing, PSR-12) | Core logic |
| Database | MySQL / MariaDB | `config/schema.sql` is single source of truth |
| Frontend | HTML5 + CSS3 + JS (ES6) | `.vt-` prefixed CSS, modular JS |
| Security | CSRF + RBAC + Sanitization at boundaries | Defined in `security.xml` |

---

## 2. Active Directories

```

vivalatable/
├── assets/          # CSS (.vt- prefix), JS (modular)
├── config/          # Database & environment config
├── includes/        # PHP classes and services
├── templates/       # HTML templates (boundary layer)
├── dev/             # Doctrine XML and developer docs
├── docs/            # Published documentation
└── install.sh       # Environment setup script

````

---

## 3. Doctrine Reference

| Charter | Scope |
|----------|-------|
| `dev/rules.xml` | Universal behavioral rules |
| `dev/code.xml` | Cross-language architecture standards |
| `dev/php.xml` | PHP coding conventions and security |
| `dev/css.xml` | Styling and theming standards |
| `dev/security.xml` | Validation / auth / RBAC / CSRF |
| `dev/database.xml` | Schema and query practices |
| `dev/env.xml` | Environment layout and references |
| `dev/circles.xml` | Circles of Trust implementation |
| `dev/git.xml` | Version-control workflow |

---

## 4. Development Workflow (from `git.xml`)

1. Work on feature branch (`lonn` or `feature/x`).
2. Merge into `main` only after validation passes.
3. Push `main` → `origin main`.
4. Pull `origin main` into production.
5. Never deploy untracked or ignored files.

---

## 5. Validation & Quality Tasks

Each task block can be executed manually or by CI to verify conformance.  
(Use `bash` or equivalent shell from project root.)

### **5.1 CSS Prefix Compliance**
```bash
grep -Rho "class=\"[^\"]*\"" templates/ | grep -v "vt-" && \
echo "❌ Unprefixed classes found." || echo "✅ All classes prefixed with .vt-"
````

### **5.2 PHP Syntax & Lint Check**

```bash
find includes templates -type f -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

### **5.3 Security Boundary Check**

```bash
grep -R "vt_service('validation.validator')" includes/ \
&& echo "❌ Validators found in managers (must move to boundary layer)" \
|| echo "✅ No validators inside managers"
```

### **5.4 CSRF Token Presence**

```bash
grep -R "<form" templates/ | grep -v "nonceField" \
&& echo "❌ Missing CSRF token in some forms" \
|| echo "✅ All forms include CSRF nonce"
```

### **5.5 Database Schema Source of Truth**

```bash
mysqldump -u root --no-data vivalatable | \
sed 's/ AUTO_INCREMENT=[0-9]*//g' | diff -u config/schema.sql - \
|| echo "✅ Schema matches database"
```
```
mysqldump -u root -p --no-data vivalatable | \sed 's/ AUTO_INCREMENT=[0-9]*//g' | diff -u config/schema.sql - || echo "✅ Schema matches database"
```

### **5.6 Security Audit Checklist**

* [ ] Input validation performed at boundary
* [ ] Output escaped for HTML / Attr / URL contexts
* [ ] All queries use prepared statements
* [ ] Sessions use HTTP-only cookies
* [ ] Passwords hashed with `password_hash()`
* [ ] CSRF tokens validated for state changes
* [ ] No `error_log()` calls (see `php.xml`)

---

## 6. Circles of Trust Implementation Checklist

| Requirement                                                      | Status |
| ---------------------------------------------------------------- | ------ |
| `VT_Conversation_Feed::list()` used for all conversation filters | [ ]    |
| Circle parameter validated (`inner/trusted/extended`)            | [ ]    |
| AJAX calls include `circle` parameter                            | [ ]    |
| UI includes circle filter buttons                                | [ ]    |
| Privacy respected at DB layer                                    | [ ]    |
| Educational UI explains anti-algorithm concept                   | [ ]    |

---

## 7. Environment Reference (from `env.xml`)

| Variable      | Value                                  |
| ------------- | -------------------------------------- |
| Local repo    | `~/Repositories/vivalatable`           |
| Dev server    | `nginx` on `localhost:8081`            |
| Docs repo     | `~/Repositories/vivalatable-docs`      |
| Error log     | `error.log` (auto-generated)           |
| Reference env | PartyMinder WordPress plugin (UI only) |

---

## 8. Security Posture (aggregate from `security.xml` + `php.xml`)

* Validation at boundary, trust in manager.
* Sanitization and escaping on input and output.
* Role-based access control with least privilege.
* HTTPS enforced for APIs.
* Session regeneration on login/logout.
* CSRF nonce required for AJAX and forms.

---

## 9. Deployment Checklist

1. `git checkout main`
2. `git merge lonn`
3. `git push origin main`
4. SSH into production → `git pull origin main`
5. Run `install.sh` (if first deploy)
6. Confirm `config/schema.sql` imported
7. Check error log and browser console for issues

---

## 10. Notes for Contributors

* Never modify doctrine XML files without consensus.
* Follow PSR-12, `.vt-` prefix, and validation boundaries.
* Update this `main.md` when workflows or standards change.
* Run **Section 5** validations before every commit.

---

**Maintainer:** Lonn Holiday
**Assistant:** Leo (GPT-5)
**Last Updated:** `2025-10-06`

```

---

### Notes
- Each shell block can be automated in CI or run manually to verify compliance.  
- The structure mirrors your doctrine files, giving one authoritative surface for both humans and scripts.  
- Future extensions could include sections for automated deployment or LLM-assisted code review (parsing XML rules).

Would you like me to prepare a version of this file with **variable placeholders replaced by actual current paths and schema names** (e.g., substituting your current DB name and local URLs from `env.xml`)?
```

