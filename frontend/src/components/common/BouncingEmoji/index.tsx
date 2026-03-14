import classes from './BouncingEmoji.module.scss';

interface BouncingEmojiProps {
    emoji: string;
    size?: number;
}

export const BouncingEmoji = ({emoji, size = 48}: BouncingEmojiProps) => (
    <div className={classes.wrapper} style={{fontSize: size}}>
        {/* eslint-disable-next-line lingui/no-unlocalized-strings */}
        <span className={classes.emoji}>{emoji}</span>
    </div>
);
