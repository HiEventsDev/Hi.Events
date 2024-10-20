# Contribution Guidelines for Hi.Events

Thank you for your interest in contributing to Hi.Events! We welcome contributions from the community and are excited to collaborate with you to improve our event management and ticket-selling platform. Before you start, please read these guidelines to ensure a smooth contribution process.

## Table of Contents

1. [How Can I Contribute?](#how-can-i-contribute)
   - [Reporting Bugs](#reporting-bugs)
   - [Suggesting Enhancements](#suggesting-enhancements)
   - [Pull Requests](#pull-requests)
2. [Development Setup](#development-setup)
   - [Style Guides](#style-guides)
      - [Coding Standards](#coding-standards)
      - [Commit Messages](#commit-messages)
   - [Translations](#translations)
      - [Backend](#backend)
      - [Frontend](#frontend)
   - [Database Changes](#database-changes)
3. [License](#license)

## How Can I Contribute?

### Reporting Bugs

If you find a bug, please report it by opening an issue in our [GitHub repository](https://github.com/HiEventsDev/hi.events/issues). Include as much detail as possible to help us diagnose and fix the issue.

### Suggesting Enhancements

We welcome suggestions for new features or improvements to existing functionality. To suggest an enhancement, please open an issue in our [GitHub repository](https://github.com/HiEventsDev/hi.events/issues) and provide a detailed description of the proposed enhancement and its benefits.

### Pull Requests

We accept pull requests for bug fixes, new features, and improvements.

⚠️ Please open an issue or discussion before starting any significant work to ensure that your contribution aligns with the project's goals.

To submit a pull request:

1. Fork the repository to your GitHub account.
2. Create a new branch from the `develop` branch for your changes (e.g., `feature/new-feature` or `bugfix/issue-123`).
3. Make your changes, ensuring that your code adheres to our coding standards.
4. Commit your changes with a descriptive commit message.
5. Push your changes to your forked repository.
6. Open a pull request to the `develop` branch in the original repository.

Please ensure that your pull request includes:

- A clear description of the changes and the problem they address.
- Any relevant issue numbers (e.g., `Fixes #123`).
- Documentation updates, if applicable.
- Tests for new functionality or bug fixes, if applicable.
- A demo or screenshots, if the changes are visual.

Once you create a pull request, a CLA bot will automatically check if you have signed the Contributor License Agreement (CLA). Signing is as simple as leaving a comment on the pull request with the message: `I have read the CLA Document and I hereby sign the CLA`. We require all contributors to sign the CLA to ensure that we have the necessary permissions to use and distribute your contributions.

## Development Setup

To set up the development environment for Hi.Events, follow the detailed instructions in our [Getting Started with Local Development guide](https://hi.events/docs/getting-started/local-development).

### Style Guides

#### Coding Standards

Please ensure that your code is well-formatted and does not contain commented-out code or unnecessary whitespace. Use descriptive variable names that follow the conventions used in the existing codebase.

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards for PHP.
- Use ES6+ features for JavaScript and adhere to the [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript).
- For React components, follow the [React/JSX Style Guide](https://github.com/airbnb/javascript/tree/master/react).

#### Commit Messages

We don't adhere to any strict commit message format, but please ensure that your messages are clear and descriptive. For guidelines, refer to [How to Write a Git Commit Message](https://chris.beams.io/posts/git-commit/).

### Translations

#### Backend

Please wrap all translatable strings in the `__()` helper function. For example:

```php
return [
    'welcome' => __('Welcome to Hi.Events!'),
];
```

#### Translation Commands

To extract messages from the codebase, use the following command:

```bash
php artisan langscanner
```

This will update the translation files in the `backend/lang` directory.

#### Frontend

[Lingui](https://lingui.dev/) is used for frontend translations. Please wrap all translatable strings in either the `t` function or `Trans` component. For example:

```jsx
import { t } from '@lingui/macro';
   
const MyComponent = () => {
    return <div>{t`Welcome to Hi.Events!`}</div>;
};
```

#### Translation Commands

To extract messages from the codebase and compile translations, use the following commands:

```bash
yarn messages:extract && yarn messages:compile
```

To list all untranslated messages, run:

```bash
cd frontend/scripts && ./list_untranslated_strings.sh
```

### Database Changes

If you are making changes to the database schema, please update the migration files accordingly.

We use [Laravel Migrations](https://laravel.com/docs/master/migrations) to manage schema changes. Migration files should only contain schema changes and no logic.

To generate a new migration file, use:

```bash
php artisan make:migration create_XXX_table
```

After running the migration, update the Domain Objects with:

```bash  
php artisan generate-domain-objects
```

This will update the Domain Objects in `backend/app/DomainObjects` based on the schema changes.

## License

By contributing to Hi.Events, you agree that your contributions will be licensed under the [AGPL-3.0 License with additional terms](LICENSE).

Thank you for contributing to Hi.Events! If you have any questions, feel free to reach out to us.
