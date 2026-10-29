export const routeStylesheets = (manifest, entries) => {
    const visited = new Set();
    const stylesheets = new Set();

    const visit = (key) => {
        const chunk = manifest[key];
        if (!chunk || chunk.isEntry || visited.has(key)) {
            return;
        }
        visited.add(key);
        (chunk.css ?? []).forEach((file) => stylesheets.add(file));
        (chunk.imports ?? []).forEach(visit);
    };

    entries.forEach(visit);

    return [...stylesheets];
};
