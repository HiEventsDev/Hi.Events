import {FC, useEffect, useRef, useState} from 'react';

interface ConfettiAnimationProps {
    duration?: number;
}

const ConfettiAnimation: FC<ConfettiAnimationProps> = ({duration = 4000}) => {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [isActive, setIsActive] = useState<boolean>(true);

    useEffect(() => {
        if (!canvasRef.current || !isActive) return;

        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        let animationFrameId: number;
        let particles: Particle[] = [];

        let shouldContinueGenerating = true;
        let lastParticleTime = Date.now();
        const particleGenerationInterval = 50;

        const resizeCanvas = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        class Particle {
            x: number;
            y: number;
            color: string;
            size: number;
            speedY: number;
            speedX: number;
            spinSpeed: number;
            spinAngle: number;
            opacity: number;
            fadeSpeed: number;
            gravity: number;
            shape: number;

            constructor(forceNew = false) {
                this.x = Math.random() * canvas.width;
                this.y = forceNew ? -20 : Math.random() * canvas.height * 0.3 - canvas.height * 0.3;

                this.color = `hsl(${Math.random() * 360}, 80%, 60%)`;
                this.size = Math.random() * 10 + 5;

                this.speedY = Math.random() * 2 + 0.5;
                this.speedX = (Math.random() - 0.5) * 1.5;
                this.spinSpeed = Math.random() * 0.2 - 0.1;
                this.spinAngle = Math.random() * Math.PI * 2;

                this.opacity = 1;
                this.fadeSpeed = Math.random() * 0.01 + 0.005;
                this.gravity = 0.03;
                this.shape = Math.floor(Math.random() * 4);
            }

            update(): boolean {
                this.y += this.speedY;
                this.x += this.speedX;
                this.spinAngle += this.spinSpeed;
                this.speedY += this.gravity;
                this.speedX += (Math.random() - 0.5) * 0.05;

                if (this.speedY > 2) {
                    this.speedY *= 0.99;
                }

                if (this.y > canvas.height * 0.3) {
                    this.opacity -= this.fadeSpeed;
                }

                return this.y <= canvas.height + 50 && this.opacity > 0;
            }

            draw(): void {
                if (!ctx) return;

                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.spinAngle);
                ctx.globalAlpha = this.opacity;
                ctx.fillStyle = this.color;

                switch (this.shape) {
                    case 0:
                        ctx.fillRect(-this.size / 2, -this.size / 2, this.size, this.size);
                        break;
                    case 1:
                        ctx.beginPath();
                        ctx.arc(0, 0, this.size / 2, 0, Math.PI * 2);
                        ctx.fill();
                        break;
                    case 2:
                        ctx.fillRect(-this.size, -this.size / 6, this.size * 2, this.size / 3);
                        break;
                    case 3:
                        this.drawStar(0, 0, 5, this.size / 2, this.size / 4);
                        break;
                }

                ctx.restore();
            }

            drawStar(cx: number, cy: number, spikes: number, outerRadius: number, innerRadius: number): void {
                if (!ctx) return;

                let rot = Math.PI / 2 * 3;
                let x = cx;
                let y = cy;
                const step = Math.PI / spikes;

                ctx.beginPath();
                ctx.moveTo(cx, cy - outerRadius);

                for (let i = 0; i < spikes; i++) {
                    x = cx + Math.cos(rot) * outerRadius;
                    y = cy + Math.sin(rot) * outerRadius;
                    ctx.lineTo(x, y);
                    rot += step;

                    x = cx + Math.cos(rot) * innerRadius;
                    y = cy + Math.sin(rot) * innerRadius;
                    ctx.lineTo(x, y);
                    rot += step;
                }

                ctx.lineTo(cx, cy - outerRadius);
                ctx.closePath();
                ctx.fill();
            }
        }

        const createInitialParticles = () => {
            for (let i = 0; i < 150; i++) {
                particles.push(new Particle());
            }
        };

        const addMoreParticles = () => {
            const now = Date.now();
            if (shouldContinueGenerating && now - lastParticleTime > particleGenerationInterval) {
                for (let i = 0; i < 10; i++) {
                    particles.push(new Particle(true));
                }
                lastParticleTime = now;
            }
        };

        createInitialParticles();
        const startTime = Date.now();

        const animate = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            addMoreParticles();

            particles = particles.filter((particle) => {
                const isAlive = particle.update();
                if (isAlive) particle.draw();
                return isAlive;
            });

            const elapsedTime = Date.now() - startTime;
            if (elapsedTime > duration) {
                shouldContinueGenerating = false;
            }

            if (particles.length > 0 || shouldContinueGenerating) {
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
