const freeRuns = (segment: string[], excluded: Set<string>): string[][] => {
    const runs: string[][] = [[]];
    for (const uid of segment) {
        if (excluded.has(uid)) {
            runs.push([]);
        } else {
            runs[runs.length - 1].push(uid);
        }
    }
    return runs.filter(run => run.length > 0);
};

const orphansInRun = (run: string[], selected: Set<string>): string[] => {
    const selectedInRun = run.filter(uid => selected.has(uid)).length;

    if (selectedInRun === 0 || run.length - selectedInRun === 1) {
        return [];
    }

    return freeRuns(run, selected).filter(remaining => remaining.length === 1).flat();
};

export const findOrphanSeats = (segments: string[][], takenUids: string[], selectedUids: string[]): string[] => {
    const taken = new Set(takenUids);
    const selected = new Set(selectedUids);

    return segments.flatMap(segment => freeRuns(segment, taken).flatMap(run => orphansInRun(run, selected)));
};
