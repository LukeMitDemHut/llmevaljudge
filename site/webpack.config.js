const Encore = require("@symfony/webpack-encore");
const path = require("path");

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || "dev");
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath("public/build/")
    // public path used by the web server to access the output path
    .setPublicPath("/build")
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry("app", "./assets/app.js")

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    .enableVueLoader()

    // Configure dev server for HMR
    .configureDevServerOptions((options) => {
        options.hot = true;
        options.host = "0.0.0.0";
        options.port = 8080;
        options.allowedHosts = "all";
        options.client = {
            webSocketURL: "ws://localhost:8080/ws",
        };
    })

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge("./assets/controllers.json")

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Displays build status system notifications to the user
    // .enableBuildNotifications()

    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = "usage";
        config.corejs = "3.38";
    })

    // enables Sass/SCSS support
    .enableSassLoader();
// uncomment if you use TypeScript
//.enableTypeScriptLoader()

// uncomment if you use React
//.enableReactPreset()

// uncomment to get integrity="..." attributes on your script & link tags
// requires WebpackEncoreBundle 1.4 or higher
//.enableIntegrityHashes(Encore.isProduction())

// uncomment if you're having problems with a jQuery plugin
//.autoProvidejQuery()

// Silence Vue ESM feature flag warnings
Encore.configureDefinePlugin((options) => {
    options.__VUE_OPTIONS_API__ = true;
    options.__VUE_PROD_DEVTOOLS__ = false;
    options.__VUE_PROD_HYDRATION_MISMATCH_DETAILS__ = false;
});

// Add aliases for cleaner imports
Encore.addAliases({
    "@": path.resolve(__dirname, "assets"),
    "@components": path.resolve(__dirname, "assets/vue/controllers/components"),
    "@pages": path.resolve(__dirname, "assets/vue/controllers/pages"),
    "@controllers": path.resolve(__dirname, "assets/vue/controllers"),
    "@stores": path.resolve(__dirname, "assets/js/stores"),
    "@services": path.resolve(__dirname, "assets/js/services"),
    "@constants": path.resolve(__dirname, "assets/js/constants"),
});

module.exports = Encore.getWebpackConfig();
