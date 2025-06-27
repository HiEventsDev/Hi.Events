import { FC, useEffect, useRef, useState } from 'react';

interface ConfettiAnimationProps {
    duration?: number;
}

const EVENT_EMOJIS = ['🎸', '🎉', '🎊', '🎈', '🎆', '✨', '🎵', '🎶', '🎤', '🎭', '🎪', '🎯', '🏆'];

const ConfettiAnimation: FC<ConfettiAnimationProps> = ({ duration = 4000 }) => {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [isActive, setIsActive] = useState(true);

    useEffect(() => {
        if (!canvasRef.current || !isActive) return;

        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let animationFrameId: number;
        const particles: Particle[] = [];
        const maxParticles = 80;
        const startTime = performance.now();

        const resizeCanvas = () => {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = window.innerWidth * ratio;
            canvas.height = window.innerHeight * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        };
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        class Particle {
            x: number;
            y: number;
            emoji: string;
            size: number;
            velocityY: number;
            velocityX: number;
            opacity: number;

            constructor() {
                this.x = Math.random() * window.innerWidth;
                this.y = -30;
                this.emoji = EVENT_EMOJIS[Math.floor(Math.random() * EVENT_EMOJIS.length)];
                this.size = Math.random() * 15 + 20; // 20–35px
                this.velocityY = Math.random() * 2 + 1; // slower: 1–3 px/frame
                this.velocityX = (Math.random() - 0.5) * 1.5; // gentle drift
                this.opacity = 1;
            }

            update(): boolean {
                this.y += this.velocityY;
                this.x += this.velocityX;
                this.velocityY += 0.05; // gentle gravity

                if (this.y > window.innerHeight * 0.7) {
                    this.opacity -= 0.015;
                }

                return this.y < window.innerHeight + 40 && this.opacity > 0;
            }

            draw(): void {
                ctx.save();
                ctx.globalAlpha = this.opacity;
                ctx.font = `${this.size}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(this.emoji, this.x, this.y);
                ctx.restore();
            }
        }

        for (let i = 0; i < 40; i++) {
            particles.push(new Particle());
        }

        const animate = (time: number) => {
            const elapsed = time - startTime;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (elapsed < duration && Math.random() < 0.05 && particles.length < maxParticles) {
                particles.push(new Particle());
            }

            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                if (p.update()) {
                    p.draw();
                } else {
                    particles.splice(i, 1);
                }
            }

            if (particles.length > 0 || elapsed < duration) {
                animationFrameId = requestAnimationFrame(animate);
            } else {
                setIsActive(false);
            }
        };

        animationFrameId = requestAnimationFrame(animate);

        return () => {
            window.removeEventListener('resize', resizeCanvas);
            cancelAnimationFrame(animationFrameId);
        };
    }, [duration, isActive]);

    if (!isActive) return null;

    return (
        <canvas
            ref={canvasRef}
            style={{
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                pointerEvents: 'none',
                zIndex: 99099,
            }}
        />
    );
};

export default ConfettiAnimation;
