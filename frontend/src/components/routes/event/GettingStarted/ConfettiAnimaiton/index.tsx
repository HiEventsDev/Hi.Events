import {FC, useEffect, useRef, useState} from 'react';

interface ConfettiAnimationProps {
    duration?: number;
}

const EVENT_EMOJIS = ['🎸', '🎉', '🎊', '🎈', '🎆', '✨', '🎵', '🎶', '🎤', '🎭', '🎪', '🎯', '🏆'];

const ConfettiAnimation: FC<ConfettiAnimationProps> = ({duration = 4000}) => {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [isActive, setIsActive] = useState<boolean>(true);

    useEffect(() => {
        if (!canvasRef.current || !isActive) return;

        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        let animationFrameId: number;
        const particles: Particle[] = [];
        const startTime = Date.now();

        // Set canvas size
        const resizeCanvas = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
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
            rotation: number;
            rotationSpeed: number;
            opacity: number;

            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = -50;
                this.emoji = EVENT_EMOJIS[Math.floor(Math.random() * EVENT_EMOJIS.length)];
                this.size = Math.random() * 20 + 20; // 20-40px
                this.velocityY = Math.random() * 3 + 2; // 2-5 px/frame
                this.velocityX = (Math.random() - 0.5) * 2; // -1 to 1 px/frame
                this.rotation = Math.random() * Math.PI * 2;
                this.rotationSpeed = (Math.random() - 0.5) * 0.2;
                this.opacity = 1;
            }

            update(): boolean {
                // Physics
                this.y += this.velocityY;
                this.x += this.velocityX;
                this.rotation += this.rotationSpeed;
                this.velocityY += 0.1; // gravity
                
                // Fade out when reaching bottom third of screen
                if (this.y > canvas.height * 0.7) {
                    this.opacity -= 0.02;
                }

                // Remove if off screen or fully transparent
                return this.y < canvas.height + 50 && this.opacity > 0;
            }

            draw(): void {
                ctx.save();
                ctx.globalAlpha = this.opacity;
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);
                ctx.font = `${this.size}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(this.emoji, 0, 0);
                ctx.restore();
            }
        }

        // Create initial burst of particles
        for (let i = 0; i < 50; i++) {
            particles.push(new Particle());
        }

        // Animation loop
        const animate = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Add new particles periodically
            const elapsed = Date.now() - startTime;
            if (elapsed < duration && Math.random() < 0.3) {
                particles.push(new Particle());
            }

            // Update and draw particles
            for (let i = particles.length - 1; i >= 0; i--) {
                const particle = particles[i];
                if (particle.update()) {
                    particle.draw();
                } else {
                    particles.splice(i, 1);
                }
            }

            // Continue animation if particles exist or still generating
            if (particles.length > 0 || elapsed < duration) {
                animationFrameId = requestAnimationFrame(animate);
            } else {
                setIsActive(false);
            }
        };

        animate();

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
