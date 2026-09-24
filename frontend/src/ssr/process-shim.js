const processShim = typeof globalThis !== "undefined" && globalThis.process
    ? globalThis.process
    : {env: {}};

export default processShim;
