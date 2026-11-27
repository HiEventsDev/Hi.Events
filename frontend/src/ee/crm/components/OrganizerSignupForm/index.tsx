import {IdParam} from "../../../../types.ts";
import {useGetOrganizerSignupForm} from "../../queries/useCrmSignupFormQueries.ts";
import {SignupFormCard} from "../SignupFormCard";

export const OrganizerSignupForm = ({organizerId, sectionClassName}: {organizerId: IdParam, sectionClassName?: string}) => {
    const {data: form} = useGetOrganizerSignupForm(organizerId);

    if (!form) {
        return null;
    }

    return (
        <section className={sectionClassName} id="newsletter" data-testid="organizer-signup-form">
            <SignupFormCard form={form} publicId={form.public_id} variant="band"/>
        </section>
    );
};
