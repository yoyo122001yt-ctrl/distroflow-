import { motion, useMotionValue, useSpring } from "motion/react";
import { useState, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { Package, Mail, Lock, ArrowRight, Zap, Shield, Cpu } from "lucide-react";
import { useAuth } from "../../contexts/AuthContext";
import toast from "react-hot-toast";

interface Particle {
  id: number;
  x: number;
  y: number;
  size: number;
  speed: number;
  angle: number;
  distance: number;
}

interface Node {
  id: number;
  x: number;
  y: number;
  vx: number;
  vy: number;
}

export default function FuturisticLogin() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [particles, setParticles] = useState<Particle[]>([]);
  const [nodes, setNodes] = useState<Node[]>([]);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const { login } = useAuth();
  const navigate = useNavigate();

  const mouseX = useMotionValue(0);
  const mouseY = useMotionValue(0);
  const smoothMouseX = useSpring(mouseX, { stiffness: 50, damping: 20 });
  const smoothMouseY = useSpring(mouseY, { stiffness: 50, damping: 20 });

  useEffect(() => {
    const newParticles: Particle[] = Array.from({ length: 100 }, (_, i) => ({
      id: i,
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      size: Math.random() * 3 + 1,
      speed: Math.random() * 0.5 + 0.2,
      angle: Math.random() * Math.PI * 2,
      distance: 0,
    }));
    setParticles(newParticles);

    const newNodes: Node[] = Array.from({ length: 30 }, (_, i) => ({
      id: i,
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      vx: (Math.random() - 0.5) * 0.5,
      vy: (Math.random() - 0.5) * 0.5,
    }));
    setNodes(newNodes);
  }, []);

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      mouseX.set(e.clientX);
      mouseY.set(e.clientY);

      setParticles((prev) =>
        prev.map((particle) => {
          const dx = e.clientX - particle.x;
          const dy = e.clientY - particle.y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          const maxDistance = 300;

          if (distance < maxDistance) {
            const force = (1 - distance / maxDistance) * 2;
            return {
              ...particle,
              x: particle.x + (dx / distance) * force * particle.speed * 5,
              y: particle.y + (dy / distance) * force * particle.speed * 5,
              distance,
            };
          }

          return {
            ...particle,
            x: particle.x + Math.cos(particle.angle) * particle.speed,
            y: particle.y + Math.sin(particle.angle) * particle.speed,
            distance,
          };
        })
      );

      setNodes((prev) =>
        prev.map((node) => {
          const dx = e.clientX - node.x;
          const dy = e.clientY - node.y;
          const distance = Math.sqrt(dx * dx + dy * dy);
          const maxDistance = 250;

          if (distance < maxDistance) {
            const force = (1 - distance / maxDistance) * 0.8;
            return {
              ...node,
              vx: node.vx + (dx / distance) * force * 0.1,
              vy: node.vy + (dy / distance) * force * 0.1,
            };
          }

          return node;
        })
      );
    };

    window.addEventListener("mousemove", handleMouseMove);
    return () => window.removeEventListener("mousemove", handleMouseMove);
  }, [mouseX, mouseY]);

  useEffect(() => {
    const interval = setInterval(() => {
      setNodes((prev) =>
        prev.map((node) => {
          let newX = node.x + node.vx;
          let newY = node.y + node.vy;
          let newVx = node.vx * 0.99;
          let newVy = node.vy * 0.99;

          if (newX < 0 || newX > window.innerWidth) {
            newVx *= -1;
            newX = Math.max(0, Math.min(window.innerWidth, newX));
          }
          if (newY < 0 || newY > window.innerHeight) {
            newVy *= -1;
            newY = Math.max(0, Math.min(window.innerHeight, newY));
          }

          return { ...node, x: newX, y: newY, vx: newVx, vy: newVy };
        })
      );

      setParticles((prev) =>
        prev.map((p) => ({
          ...p,
          x: ((p.x % window.innerWidth) + window.innerWidth) % window.innerWidth,
          y: ((p.y % window.innerHeight) + window.innerHeight) % window.innerHeight,
        }))
      );
    }, 1000 / 60);

    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const draw = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      nodes.forEach((node, i) => {
        nodes.slice(i + 1).forEach((otherNode) => {
          const dx = node.x - otherNode.x;
          const dy = node.y - otherNode.y;
          const distance = Math.sqrt(dx * dx + dy * dy);

          if (distance < 150) {
            const opacity = (1 - distance / 150) * 0.5;
            ctx.strokeStyle = `rgba(59, 130, 246, ${opacity})`;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(node.x, node.y);
            ctx.lineTo(otherNode.x, otherNode.y);
            ctx.stroke();
          }
        });
      });

      requestAnimationFrame(draw);
    };

    draw();
  }, [nodes]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    if (!email || !password) {
      setError("Please fill in all fields");
      return;
    }
    setLoading(true);
    try {
      await login(email, password);
      toast.success("Welcome back!");
      navigate("/");
    } catch (err: any) {
      setError(err.message || "Invalid credentials");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative min-h-screen overflow-hidden bg-black">
      {/* Animated Grid Background */}
      <div className="absolute inset-0">
        <motion.div
          className="absolute inset-0"
          style={{
            backgroundImage: `
              linear-gradient(rgba(59, 130, 246, 0.1) 1px, transparent 1px),
              linear-gradient(90deg, rgba(59, 130, 246, 0.1) 1px, transparent 1px)
            `,
            backgroundSize: "50px 50px",
          }}
          animate={{
            backgroundPosition: ["0px 0px", "50px 50px"],
          }}
          transition={{
            duration: 20,
            repeat: Infinity,
            ease: "linear",
          }}
        />
      </div>

      {/* Neural Network Canvas */}
      <canvas
        ref={canvasRef}
        className="absolute inset-0 pointer-events-none"
        style={{ opacity: 0.6 }}
      />

      {/* Magnetic Particles */}
      <div className="absolute inset-0 pointer-events-none">
        {particles.map((particle) => {
          const glowIntensity = Math.max(0, 1 - particle.distance / 300);
          return (
            <motion.div
              key={particle.id}
              className="absolute rounded-full"
              style={{
                left: particle.x,
                top: particle.y,
                width: particle.size,
                height: particle.size,
                background: `radial-gradient(circle, 
                  rgba(59, 130, 246, ${0.8 + glowIntensity * 0.2}), 
                  rgba(139, 92, 246, ${0.4 + glowIntensity * 0.3}))`,
                boxShadow: `0 0 ${10 + glowIntensity * 20}px rgba(59, 130, 246, ${glowIntensity})`,
              }}
            />
          );
        })}
      </div>

      {/* Neural Network Nodes */}
      {nodes.map((node) => (
        <motion.div
          key={node.id}
          className="absolute w-2 h-2 rounded-full bg-blue-400"
          style={{
            left: node.x,
            top: node.y,
            boxShadow: "0 0 10px rgba(59, 130, 246, 0.8)",
          }}
        />
      ))}

      {/* Holographic Scanlines */}
      <motion.div
        className="absolute inset-0 pointer-events-none opacity-10"
        style={{
          backgroundImage: `repeating-linear-gradient(
            0deg,
            rgba(59, 130, 246, 0.1) 0px,
            transparent 1px,
            transparent 2px,
            rgba(59, 130, 246, 0.1) 3px
          )`,
        }}
        animate={{
          y: [0, -3],
        }}
        transition={{
          duration: 0.1,
          repeat: Infinity,
          ease: "linear",
        }}
      />

      {/* Magnetic Ripple Effect */}
      <motion.div
        className="absolute rounded-full pointer-events-none border-2 border-blue-500/30"
        style={{
          left: smoothMouseX,
          top: smoothMouseY,
          x: "-50%",
          y: "-50%",
        }}
        animate={{
          width: [0, 300],
          height: [0, 300],
          opacity: [0.6, 0],
        }}
        transition={{
          duration: 1.5,
          repeat: Infinity,
          ease: "easeOut",
        }}
      />

      <motion.div
        className="absolute rounded-full pointer-events-none border-2 border-purple-500/30"
        style={{
          left: smoothMouseX,
          top: smoothMouseY,
          x: "-50%",
          y: "-50%",
        }}
        animate={{
          width: [0, 400],
          height: [0, 400],
          opacity: [0.4, 0],
        }}
        transition={{
          duration: 2,
          repeat: Infinity,
          ease: "easeOut",
          delay: 0.3,
        }}
      />

      {/* Floating Geometric Shapes */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden">
        {[...Array(6)].map((_, i) => (
          <motion.div
            key={i}
            className="absolute"
            style={{
              left: `${15 + i * 15}%`,
              top: `${20 + (i % 3) * 25}%`,
            }}
            animate={{
              y: [0, -30, 0],
              rotate: [0, 180, 360],
              scale: [1, 1.2, 1],
            }}
            transition={{
              duration: 10 + i * 2,
              repeat: Infinity,
              ease: "easeInOut",
              delay: i * 0.5,
            }}
          >
            <div
              className="w-20 h-20 border border-cyan-400/30"
              style={{
                clipPath:
                  i % 3 === 0
                    ? "polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)"
                    : i % 3 === 1
                    ? "polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%)"
                    : "polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%)",
                boxShadow: `0 0 20px rgba(34, 211, 238, 0.3)`,
              }}
            />
          </motion.div>
        ))}
      </div>

      {/* Energy Field Glow */}
      <motion.div
        className="absolute rounded-full blur-3xl pointer-events-none"
        style={{
          width: 600,
          height: 600,
          left: smoothMouseX,
          top: smoothMouseY,
          x: "-50%",
          y: "-50%",
          background: `radial-gradient(circle, 
            rgba(59, 130, 246, 0.15) 0%, 
            rgba(139, 92, 246, 0.1) 50%, 
            transparent 70%)`,
        }}
      />

      {/* Main Content */}
      <div className="relative z-10 min-h-screen flex items-center justify-center p-4">
        <motion.div
          className="w-full max-w-md"
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.8, type: "spring" }}
        >
          {/* Futuristic Logo */}
          <motion.div
            className="text-center mb-12"
            initial={{ opacity: 0, y: -30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3, duration: 0.8 }}
          >
            <motion.div className="relative inline-block mb-6">
              {[...Array(3)].map((_, i) => (
                <motion.div
                  key={i}
                  className="absolute top-1/2 left-1/2"
                  style={{
                    width: 80 + i * 30,
                    height: 80 + i * 30,
                    marginLeft: -(40 + i * 15),
                    marginTop: -(40 + i * 15),
                  }}
                  animate={{ rotate: 360 }}
                  transition={{
                    duration: 10 + i * 5,
                    repeat: Infinity,
                    ease: "linear",
                  }}
                >
                  <div
                    className="w-full h-full rounded-full border border-cyan-400/30"
                    style={{
                      boxShadow: `0 0 20px rgba(34, 211, 238, 0.2)`,
                    }}
                  />
                  <motion.div
                    className="absolute top-0 left-1/2 w-2 h-2 -ml-1 -mt-1 rounded-full bg-cyan-400"
                    style={{ boxShadow: "0 0 10px rgba(34, 211, 238, 0.8)" }}
                  />
                </motion.div>
              ))}

              <motion.div
                className="relative z-10 inline-flex items-center justify-center w-20 h-20 rounded-2xl"
                style={{
                  background: `linear-gradient(135deg, 
                    rgba(59, 130, 246, 0.2), 
                    rgba(139, 92, 246, 0.2))`,
                  backdropFilter: "blur(10px)",
                  border: "1px solid rgba(59, 130, 246, 0.3)",
                  boxShadow: `
                    0 0 30px rgba(59, 130, 246, 0.4),
                    inset 0 0 20px rgba(59, 130, 246, 0.1)
                  `,
                }}
                animate={{
                  boxShadow: [
                    "0 0 30px rgba(59, 130, 246, 0.4), inset 0 0 20px rgba(59, 130, 246, 0.1)",
                    "0 0 50px rgba(139, 92, 246, 0.6), inset 0 0 30px rgba(139, 92, 246, 0.2)",
                    "0 0 30px rgba(59, 130, 246, 0.4), inset 0 0 20px rgba(59, 130, 246, 0.1)",
                  ],
                }}
                transition={{ duration: 3, repeat: Infinity }}
              >
                <Package className="text-cyan-400" size={36} />
              </motion.div>
            </motion.div>

            <motion.h1 className="text-6xl font-bold mb-3 relative">
              <motion.span
                className="relative inline-block"
                style={{
                  background: `linear-gradient(135deg, 
                    #22D3EE 0%, 
                    #3B82F6 50%, 
                    #8B5CF6 100%)`,
                  WebkitBackgroundClip: "text",
                  WebkitTextFillColor: "transparent",
                  backgroundClip: "text",
                  textShadow: "0 0 30px rgba(59, 130, 246, 0.5)",
                }}
                animate={{
                  textShadow: [
                    "0 0 30px rgba(59, 130, 246, 0.5)",
                    "0 0 50px rgba(139, 92, 246, 0.8)",
                    "0 0 30px rgba(59, 130, 246, 0.5)",
                  ],
                }}
                transition={{ duration: 2, repeat: Infinity }}
              >
                DistroFlow
              </motion.span>

              <motion.span
                className="absolute inset-0"
                style={{
                  background: `linear-gradient(135deg, #22D3EE 0%, #3B82F6 50%, #8B5CF6 100%)`,
                  WebkitBackgroundClip: "text",
                  WebkitTextFillColor: "transparent",
                  backgroundClip: "text",
                  opacity: 0,
                }}
                animate={{
                  opacity: [0, 0.3, 0],
                  x: [-2, 2, -2],
                }}
                transition={{
                  duration: 0.3,
                  repeat: Infinity,
                  repeatDelay: 3,
                }}
              >
                DistroFlow
              </motion.span>
            </motion.h1>

            <motion.div
              className="h-0.5 bg-gradient-to-r from-transparent via-cyan-400 to-transparent mx-auto mb-4"
              initial={{ width: 0 }}
              animate={{ width: "280px" }}
              transition={{ duration: 1.5, delay: 0.5 }}
            />

            <motion.p
              className="text-cyan-400/80 text-sm tracking-wider uppercase"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.8 }}
            >
              Next-Gen Distribution System
            </motion.p>
          </motion.div>

          {/* Ultra-Futuristic Login Card */}
          <motion.div
            className="relative"
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5, duration: 0.8 }}
          >
            <motion.div
              className="absolute -inset-[1px] rounded-3xl opacity-75"
              style={{
                background: `linear-gradient(135deg, 
                  rgba(34, 211, 238, 0.5), 
                  rgba(59, 130, 246, 0.5), 
                  rgba(139, 92, 246, 0.5))`,
              }}
              animate={{
                background: [
                  "linear-gradient(0deg, rgba(34, 211, 238, 0.5), rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.5))",
                  "linear-gradient(360deg, rgba(34, 211, 238, 0.5), rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.5))",
                ],
              }}
              transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
            />

            <div
              className="relative backdrop-blur-2xl bg-gradient-to-br from-slate-900/80 via-blue-950/80 to-slate-900/80 rounded-3xl p-8 border border-cyan-500/20"
              style={{
                boxShadow: `
                  0 0 40px rgba(34, 211, 238, 0.1),
                  inset 0 0 40px rgba(34, 211, 238, 0.05)
                `,
              }}
            >
              <div className="flex items-center justify-between mb-8">
                <h2 className="text-2xl font-bold text-cyan-400 flex items-center gap-3">
                  <motion.div
                    animate={{ rotate: 360 }}
                    transition={{ duration: 4, repeat: Infinity, ease: "linear" }}
                  >
                    <Shield className="text-blue-400" size={24} />
                  </motion.div>
                  Secure Access
                </h2>
                <motion.div
                  animate={{
                    scale: [1, 1.2, 1],
                    opacity: [0.5, 1, 0.5],
                  }}
                  transition={{ duration: 2, repeat: Infinity }}
                >
                  <Cpu className="text-purple-400" size={24} />
                </motion.div>
              </div>

              {error && (
                <div className="mb-4 p-3 bg-red-500/20 border border-red-500/30 rounded-xl text-red-300 text-sm text-center">
                  {error}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-6">
                <motion.div
                  whileHover={{ scale: 1.02 }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  <label className="block text-sm font-medium text-cyan-400/80 mb-2 uppercase tracking-wide">
                    Email ID
                  </label>
                  <div className="relative group">
                    <motion.div
                      className="absolute -inset-[1px] rounded-xl opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
                      style={{
                        background: "linear-gradient(90deg, #22D3EE, #3B82F6)",
                      }}
                      transition={{ duration: 0.3 }}
                    />
                    <div className="relative flex items-center">
                      <Mail
                        className="absolute left-4 text-cyan-400/60 z-10"
                        size={20}
                      />
                      <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="w-full pl-12 pr-4 py-3 bg-slate-900/50 backdrop-blur-xl border border-cyan-500/30 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-400 transition-all relative"
                        placeholder="user@distroflow.ai"
                        required
                        style={{
                          boxShadow: "inset 0 2px 10px rgba(0, 0, 0, 0.3)",
                        }}
                      />
                    </div>
                  </div>
                </motion.div>

                <motion.div
                  whileHover={{ scale: 1.02 }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  <label className="block text-sm font-medium text-cyan-400/80 mb-2 uppercase tracking-wide">
                    Access Code
                  </label>
                  <div className="relative group">
                    <motion.div
                      className="absolute -inset-[1px] rounded-xl opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
                      style={{
                        background: "linear-gradient(90deg, #3B82F6, #8B5CF6)",
                      }}
                      transition={{ duration: 0.3 }}
                    />
                    <div className="relative flex items-center">
                      <Lock
                        className="absolute left-4 text-cyan-400/60 z-10"
                        size={20}
                      />
                      <input
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        className="w-full pl-12 pr-4 py-3 bg-slate-900/50 backdrop-blur-xl border border-cyan-500/30 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:border-cyan-400 transition-all relative"
                        placeholder="••••••••••••"
                        required
                        style={{
                          boxShadow: "inset 0 2px 10px rgba(0, 0, 0, 0.3)",
                        }}
                      />
                    </div>
                  </div>
                </motion.div>

                <div className="flex items-center justify-between text-sm">
                  <label className="flex items-center gap-2 text-cyan-400/70 cursor-pointer group">
                    <input
                      type="checkbox"
                      className="w-4 h-4 rounded border-cyan-500/30 bg-slate-900/50 text-cyan-500 focus:ring-cyan-500 focus:ring-offset-slate-900"
                    />
                    <span className="group-hover:text-cyan-400 transition-colors">
                      Remember Device
                    </span>
                  </label>
                  <a
                    href="#"
                    className="text-purple-400 hover:text-purple-300 transition-colors"
                  >
                    Recovery Options
                  </a>
                </div>

                <motion.button
                  type="submit"
                  className="w-full relative py-4 px-6 rounded-xl font-semibold text-white overflow-hidden group"
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  disabled={loading}
                >
                  <motion.div
                    className="absolute inset-0"
                    style={{
                      background: "linear-gradient(90deg, #22D3EE, #3B82F6, #8B5CF6, #22D3EE)",
                      backgroundSize: "200% 100%",
                    }}
                    animate={{
                      backgroundPosition: ["0% 0%", "200% 0%"],
                    }}
                    transition={{
                      duration: 2,
                      repeat: Infinity,
                      ease: "linear",
                    }}
                  />

                  <motion.div
                    className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent"
                    animate={{
                      x: ["-100%", "200%"],
                    }}
                    transition={{
                      duration: 1.5,
                      repeat: Infinity,
                      repeatDelay: 0.5,
                    }}
                  />

                  <span className="relative flex items-center justify-center gap-2 uppercase tracking-wider">
                    {loading ? (
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white" />
                    ) : (
                      <>
                        <Zap size={20} />
                        Initialize Access
                        <ArrowRight size={20} />
                      </>
                    )}
                  </span>
                </motion.button>
              </form>

              <div className="relative my-8">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-cyan-500/20"></div>
                </div>
                <div className="relative flex justify-center text-sm">
                  <span className="px-4 bg-transparent text-cyan-400/60 uppercase tracking-wider">
                    Biometric Auth
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="py-3 px-4 bg-gradient-to-r from-slate-900/80 to-blue-950/80 border border-cyan-500/30 rounded-xl text-cyan-400 font-medium hover:border-cyan-400/50 transition-all backdrop-blur-xl"
                  style={{
                    boxShadow: "0 4px 20px rgba(34, 211, 238, 0.1)",
                  }}
                >
                  FaceID
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="py-3 px-4 bg-gradient-to-r from-slate-900/80 to-purple-950/80 border border-purple-500/30 rounded-xl text-purple-400 font-medium hover:border-purple-400/50 transition-all backdrop-blur-xl"
                  style={{
                    boxShadow: "0 4px 20px rgba(139, 92, 246, 0.1)",
                  }}
                >
                  Fingerprint
                </motion.button>
              </div>

              <p className="mt-8 text-center text-sm text-gray-500">
                New to the system?{" "}
                <a
                  href="#"
                  className="text-cyan-400 hover:text-cyan-300 font-semibold transition-colors"
                >
                  Request Access Clearance
                </a>
              </p>
            </div>
          </motion.div>

          <motion.div
            className="mt-8 text-center"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1 }}
          >
            <div className="inline-flex items-center gap-2 text-sm text-cyan-400/60">
              <motion.div
                className="w-2 h-2 rounded-full bg-green-400"
                animate={{
                  opacity: [0.3, 1, 0.3],
                  scale: [1, 1.3, 1],
                }}
                transition={{ duration: 2, repeat: Infinity }}
                style={{
                  boxShadow: "0 0 10px rgba(34, 197, 94, 0.8)",
                }}
              />
              System Status: Online • Quantum Encryption Active
            </div>
          </motion.div>
        </motion.div>
      </div>

      {[
        { top: 0, left: 0, rotate: 0 },
        { top: 0, right: 0, rotate: 90 },
        { bottom: 0, right: 0, rotate: 180 },
        { bottom: 0, left: 0, rotate: 270 },
      ].map((pos, i) => (
        <motion.div
          key={i}
          className="absolute w-32 h-32 pointer-events-none"
          style={{
            ...pos,
            background: `radial-gradient(circle at ${
              pos.top !== undefined ? "top" : "bottom"
            } ${pos.left !== undefined ? "left" : "right"}, 
              rgba(59, 130, 246, 0.2) 0%, 
              transparent 70%)`,
          }}
          animate={{
            opacity: [0.3, 0.6, 0.3],
          }}
          transition={{
            duration: 3,
            repeat: Infinity,
            delay: i * 0.5,
          }}
        />
      ))}
    </div>
  );
}
