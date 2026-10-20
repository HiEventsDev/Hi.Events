export interface CompanionRuleSeat {
    acc: boolean;
    comp: boolean;
}

export const excessCompanionSeats = (seats: CompanionRuleSeat[]): number =>
    Math.max(0, seats.filter(seat => seat.comp).length - seats.filter(seat => seat.acc).length);
