const fs = require('fs');
const path = require('path');
const { defineConfig } = require('cypress');
const createBundler = require('@bahmutov/cypress-esbuild-preprocessor');
const { addCucumberPreprocessorPlugin } = require('@badeball/cypress-cucumber-preprocessor');
const { createEsbuildPlugin } = require('@badeball/cypress-cucumber-preprocessor/esbuild');

/* fetch.php legge il nome della fixture da questo file (vedi "MODALITÀ FIXTURE") */
const FLAG = path.join(__dirname, 'cache', '.fixture');
const FIXTURE_CACHE = path.join(__dirname, 'cache', 'fixture.json');

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost/fipav_proxy/',
    specPattern: 'cypress/e2e/**/*.feature',
    supportFile: false,
    async setupNodeEvents(on, config) {
      await addCucumberPreprocessorPlugin(on, config);
      on('file:preprocessor', createBundler({ plugins: [createEsbuildPlugin(config)] }));
      on('task', {
        /* nome = usa cypress/fixtures/fipav/<nome>.html; null = torna a FIPAV vero */
        setFixture(name) {
          if (name) fs.writeFileSync(FLAG, name);
          else fs.rmSync(FLAG, { force: true });
          return null;
        },
        clearFixtureCache() {
          fs.rmSync(FIXTURE_CACHE, { force: true });
          return null;
        },
      });
      /* A fine run il sito locale torna a leggere FIPAV */
      on('after:run', () => fs.rmSync(FLAG, { force: true }));
      return config;
    },
  },
});
