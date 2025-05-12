import {useEffect, useRef, useState} from 'react';

const ConfettiAnimation = ({duration = 4000}) => {
    const canvasRef = useRef(null);
    const [isActive, setIsActive] = useState(true);

    useEffect(() => {
        if (!canvasRef.current || !isActive) return;

        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        let animationFrameId;
        let particles = [];

        // Track animation state
        let shouldContinueGenerating = true;
        let lastParticleTime = Date.now();
        const particleGenerationInterval = 50; // ms between new particle batches

        // Set canvas to full window size
        const resizeCanvas = () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Confetti particle class
        class Particle {
            constructor(forceNew = false) {
                // Start position - either at the top or slightly above the visible area
                if (forceNew) {
                    // Start new particles from the top
                    this.x = Math.random() * canvas.width;
                    this.y = -20; // Just above the visible area
                } else {
                    // Initial particles can start anywhere in the top portion
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height * 0.3 - canvas.height * 0.3;
                }

                // Appearance
                this.color = `hsl(${Math.random() * 360}, 80%, 60%)`;
                this.size = Math.random() * 10 + 5; // Slightly larger particles

                // Movement
                this.speedY = Math.random() * 2 + 0.5; // Slower fall
                this.speedX = (Math.random() - 0.5) * 1.5; // Gentler horizontal movement
                this.spinSpeed = Math.random() * 0.2 - 0.1;
                this.spinAngle = Math.random() * Math.PI * 2;

                // Lifespan management
                this.opacity = 1;
                this.fadeSpeed = Math.random() * 0.01 + 0.005; // Much slower fade
                this.gravity = 0.03; // Less gravity

                // Shape variety
                this.shape = Math.floor(Math.random() * 4); // 0: square, 1: circle, 2: line, 3: star
            }

            update() {
                this.y += this.speedY;
                this.x += this.speedX;
                this.spinAngle += this.spinSpeed;

                // Apply a gentler gravity effect
                this.speedY += this.gravity;

                // Apply very slight wind effect with directional change
                this.speedX += (Math.random() - 0.5) * 0.05;

                // Slow down particles as they fall (air resistance simulation)
                if (this.speedY > 2) {
                    this.speedY *= 0.99;
                }

                // Only start fading after they've been visible for a while
                if (this.y > canvas.height * 0.3) {
                    this.opacity -= this.fadeSpeed;
                }

                // Reset particle if it goes offscreen or becomes invisible
                if (this.y > canvas.height + 50 || this.opacity <= 0) {
                    return false;
                }
                return true;
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.spinAngle);
                ctx.globalAlpha = this.opacity;
                ctx.fillStyle = this.color;

                switch (this.shape) {
                    case 0: // square
                        ctx.fillRect(-this.size / 2, -this.size / 2, this.size, this.size);
                        break;
                    case 1: // circle
                        ctx.beginPath();
                        ctx.arc(0, 0, this.size / 2, 0, Math.PI * 2);
                        ctx.fill();
                        break;
                    case 2: // rectangle
                        ctx.fillRect(-this.size, -this.size / 6, this.size * 2, this.size / 3);
                        break;
                    case 3: // star
                        this.drawStar(0, 0, 5, this.size / 2, this.size / 4);
                        break;
                }

                ctx.restore();
            }

            drawStar(cx, cy, spikes, outerRadius, innerRadius) {
                let rot = Math.PI / 2 * 3;
                let x = cx;
                let y = cy;
                let step = Math.PI / spikes;

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

        // Initialize particles
        const createInitialParticles = () => {
            for (let i = 0; i < 150; i++) {
                particles.push(new Particle());
            }
        };

        // Add more particles periodically
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

        // Animation loop
        const startTime = Date.now();

        const animate = () => {
            // Clear the canvas completely for a transparent background
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Add more particles if needed
            addMoreParticles();

            // Update and draw particles
            particles = particles.filter(particle => {
                const isAlive = particle.update();
                if (isAlive) particle.draw();
                return isAlive;
            });

            // Check if we should continue generating new particles
            const elapsedTime = Date.now() - startTime;
            if (elapsedTime > duration) {
                shouldContinueGenerating = false;
            }

            // Continue animation as long as there are particles or we're still generating
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

    // Only render the canvas when active
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
