import {ChangeEvent, ClipboardEvent, Fragment, useEffect, useId, useRef, useState} from "react";
import classNames from "classnames";
import classes from "./OneTimeCodeInput.module.scss";

interface OneTimeCodeInputProps {
    value: string;
    onChange: (value: string) => void;
    onComplete?: (value: string) => void;
    label: string;
    length?: number;
    error?: string | null;
    disabled?: boolean;
    autoFocus?: boolean;
    dataTestId?: string;
}

const digitsOnly = (value: string) => value.replace(/\D/g, '');

export const OneTimeCodeInput = ({
                                     value,
                                     onChange,
                                     onComplete,
                                     label,
                                     length = 6,
                                     error,
                                     disabled,
                                     autoFocus,
                                     dataTestId,
                                 }: OneTimeCodeInputProps) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const [focused, setFocused] = useState(false);
    const [shaking, setShaking] = useState(false);
    const errorId = useId();
    const midpoint = Math.ceil(length / 2);

    useEffect(() => {
        if (error) {
            setShaking(true);
            inputRef.current?.focus();
        }
    }, [error]);

    useEffect(() => {
        if (autoFocus && !disabled) {
            inputRef.current?.focus();
        }
    }, [autoFocus, disabled]);

    const update = (raw: string) => {
        const next = digitsOnly(raw).slice(0, length);
        onChange(next);

        if (next.length === length && next !== value) {
            onComplete?.(next);
        }
    };

    const handlePaste = (event: ClipboardEvent<HTMLInputElement>) => {
        event.preventDefault();
        update(event.clipboardData.getData('text'));
    };

    const keepCaretAtEnd = () => {
        const input = inputRef.current;
        if (input && input.selectionStart !== input.value.length) {
            input.setSelectionRange(input.value.length, input.value.length);
        }
    };

    return (
        <div className={classes.wrapper}>
            <div
                onAnimationEnd={(event) => event.target === event.currentTarget && setShaking(false)}
                className={classNames(classes.field, {
                    [classes.focused]: focused,
                    [classes.invalid]: !!error,
                    [classes.shake]: shaking,
                    [classes.disabled]: disabled,
                })}
            >
                <input
                    ref={inputRef}
                    className={classes.input}
                    value={value}
                    onChange={(event: ChangeEvent<HTMLInputElement>) => update(event.target.value)}
                    onPaste={handlePaste}
                    onFocus={() => setFocused(true)}
                    onBlur={() => setFocused(false)}
                    onSelect={keepCaretAtEnd}
                    disabled={disabled}
                    aria-label={label}
                    aria-invalid={!!error}
                    aria-describedby={error ? errorId : undefined}
                    autoComplete="one-time-code"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={length}
                    enterKeyHint="done"
                    spellCheck={false}
                    name="one-time-code"
                    data-testid={dataTestId}
                />
                <div className={classes.slots} aria-hidden="true">
                    {Array.from({length}).map((_, index) => {
                        const isActive = focused && (index === value.length || (value.length === length && index === length - 1));
                        return (
                            <Fragment key={index}>
                                {index === midpoint && <span className={classes.separator}/>}
                                <div
                                    className={classNames(classes.slot, {
                                        [classes.filled]: index < value.length,
                                        [classes.active]: isActive,
                                    })}
                                >
                                    {value[index] ?? ''}
                                    {isActive && index === value.length && <span className={classes.caret}/>}
                                </div>
                            </Fragment>
                        );
                    })}
                </div>
            </div>
            {error && <div id={errorId} className={classes.error} role="alert">{error}</div>}
        </div>
    );
};
