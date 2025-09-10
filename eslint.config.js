import js from '@eslint/js';
import eslintPluginReact from 'eslint-plugin-react';
import eslintPluginImport from 'eslint-plugin-import';

export default [
  js.configs.recommended,
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: {
        window: 'readonly',
        document: 'readonly',
        fetch: 'readonly',
        alert: 'readonly',
        setTimeout: 'readonly',
        console: 'readonly',
        history: 'readonly',
        localStorage: 'readonly',
        HTMLElement: 'readonly',
        navigator: 'readonly',
        // Add more browser globals if needed
      },
    },
    plugins: {
      react: eslintPluginReact,
      import: eslintPluginImport,
    },
    rules: {
      quotes: ['error', 'single'],
      'import/extensions': ['error', 'never', { json: 'always' }],
      'react/jsx-uses-react': 'off',
      'react/react-in-jsx-scope': 'off',
      'react/no-unknown-property': 'error',
      // Add more strict JSX rules if needed
    },
    settings: {
      react: {
        pragma: 'h',
        version: 'detect',
      },
    },
  },
];