/**
 * Stub for the optional monaco-editor dependency of jsonschema-builder-vue.
 *
 * The library dynamically imports monaco only for its JSON code panel. We use
 * the visual editor exclusively and do not bundle monaco; if that panel is
 * enabled anyway, the editor component shows its load-error state.
 */
export default {};