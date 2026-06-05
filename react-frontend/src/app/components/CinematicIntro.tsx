import { motion, AnimatePresence } from "motion/react";
import { useEffect, useState } from "react";
import { Package, Truck, Zap } from "lucide-react";

interface CinematicIntroProps {
  onComplete: () => void;
}

export default function CinematicIntro({ onComplete }: CinematicIntroProps) {
  const [stage, setStage] = useState(0);

  useEffect(() => {
    const timers = [
      setTimeout(() => setStage(1), 500),
      setTimeout(() => setStage(2), 1800),
      setTimeout(() => setStage(3), 3200),
      setTimeout(() => onComplete(), 4500),
    ];

    return () => timers.forEach(clearTimeout);
  }, [onComplete]);

  return (
    <AnimatePresence>
      <motion.div
        className="fixed inset-0 z-50 overflow-hidden"
        initial={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.8 }}
      >
        {/* Animated Background */}
        <div className="absolute inset-0 bg-gradient-to-br from-[#0F172A] via-[#1E293B] to-[#334155]">
          {/* Animated Grid */}
          <motion.div
            className="absolute inset-0 opacity-20"
            style={{
              backgroundImage: `
                linear-gradient(rgba(59, 130, 246, 0.3) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.3) 1px, transparent 1px)
              `,
              backgroundSize: "50px 50px",
            }}
            initial={{ opacity: 0, scale: 1.2 }}
            animate={{ opacity: 0.2, scale: 1 }}
            transition={{ duration: 2 }}
          />

          {/* Floating Particles */}
          {[...Array(30)].map((_, i) => (
            <motion.div
              key={i}
              className="absolute w-1 h-1 bg-blue-400 rounded-full"
              initial={{
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                opacity: 0,
              }}
              animate={{
                y: [
                  Math.random() * window.innerHeight,
                  Math.random() * window.innerHeight - 200,
                ],
                opacity: [0, 1, 0],
              }}
              transition={{
                duration: Math.random() * 3 + 2,
                repeat: Infinity,
                delay: Math.random() * 2,
              }}
            />
          ))}

          {/* Light Beams */}
          <motion.div
            className="absolute top-0 left-1/4 w-1 h-full bg-gradient-to-b from-blue-400/50 via-blue-500/20 to-transparent"
            initial={{ scaleY: 0, opacity: 0 }}
            animate={{ scaleY: 1, opacity: 1 }}
            transition={{ duration: 1.5, delay: 0.3 }}
          />
          <motion.div
            className="absolute top-0 right-1/4 w-1 h-full bg-gradient-to-b from-purple-400/50 via-purple-500/20 to-transparent"
            initial={{ scaleY: 0, opacity: 0 }}
            animate={{ scaleY: 1, opacity: 1 }}
            transition={{ duration: 1.5, delay: 0.5 }}
          />
        </div>

        {/* Content Container */}
        <div className="relative z-10 flex items-center justify-center h-full">
          <div className="text-center">
            {/* Stage 1: Icon Animation */}
            <AnimatePresence>
              {stage >= 1 && (
                <motion.div
                  initial={{ scale: 0, rotate: -180 }}
                  animate={{ scale: 1, rotate: 0 }}
                  exit={{ scale: 0, opacity: 0 }}
                  transition={{
                    type: "spring",
                    stiffness: 200,
                    damping: 20,
                  }}
                  className="mb-8 flex justify-center gap-6"
                >
                  {/* Animated Icons */}
                  <motion.div
                    animate={{
                      y: [0, -10, 0],
                      rotate: [0, 5, 0],
                    }}
                    transition={{
                      duration: 2,
                      repeat: Infinity,
                      delay: 0,
                    }}
                  >
                    <div className="relative">
                      <motion.div
                        className="absolute inset-0 bg-blue-500 rounded-full blur-xl opacity-50"
                        animate={{
                          scale: [1, 1.2, 1],
                          opacity: [0.5, 0.8, 0.5],
                        }}
                        transition={{ duration: 2, repeat: Infinity }}
                      />
                      <Package size={48} className="relative text-blue-400" />
                    </div>
                  </motion.div>

                  <motion.div
                    animate={{
                      y: [0, -10, 0],
                      rotate: [0, -5, 0],
                    }}
                    transition={{
                      duration: 2,
                      repeat: Infinity,
                      delay: 0.3,
                    }}
                  >
                    <div className="relative">
                      <motion.div
                        className="absolute inset-0 bg-green-500 rounded-full blur-xl opacity-50"
                        animate={{
                          scale: [1, 1.2, 1],
                          opacity: [0.5, 0.8, 0.5],
                        }}
                        transition={{
                          duration: 2,
                          repeat: Infinity,
                          delay: 0.3,
                        }}
                      />
                      <Truck size={48} className="relative text-green-400" />
                    </div>
                  </motion.div>

                  <motion.div
                    animate={{
                      y: [0, -10, 0],
                      rotate: [0, 5, 0],
                    }}
                    transition={{
                      duration: 2,
                      repeat: Infinity,
                      delay: 0.6,
                    }}
                  >
                    <div className="relative">
                      <motion.div
                        className="absolute inset-0 bg-purple-500 rounded-full blur-xl opacity-50"
                        animate={{
                          scale: [1, 1.2, 1],
                          opacity: [0.5, 0.8, 0.5],
                        }}
                        transition={{
                          duration: 2,
                          repeat: Infinity,
                          delay: 0.6,
                        }}
                      />
                      <Zap size={48} className="relative text-purple-400" />
                    </div>
                  </motion.div>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Stage 2: Brand Name */}
            <AnimatePresence>
              {stage >= 2 && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -20 }}
                  transition={{ duration: 0.8 }}
                >
                  <motion.h1
                    className="text-7xl font-bold mb-4 bg-gradient-to-r from-blue-400 via-purple-400 to-green-400 bg-clip-text text-transparent"
                    initial={{ scale: 0.8, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{
                      duration: 1,
                      type: "spring",
                      stiffness: 100,
                    }}
                  >
                    {"DistroFlow".split("").map((char, index) => (
                      <motion.span
                        key={index}
                        initial={{ opacity: 0, y: 50 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                          duration: 0.5,
                          delay: index * 0.1,
                        }}
                      >
                        {char}
                      </motion.span>
                    ))}
                  </motion.h1>

                  {/* Glowing underline */}
                  <motion.div
                    className="h-1 bg-gradient-to-r from-transparent via-blue-500 to-transparent mx-auto"
                    initial={{ width: 0 }}
                    animate={{ width: "300px" }}
                    transition={{ duration: 1, delay: 1 }}
                  />
                </motion.div>
              )}
            </AnimatePresence>

            {/* Stage 3: Tagline */}
            <AnimatePresence>
              {stage >= 3 && (
                <motion.p
                  className="text-xl text-gray-300 mt-6"
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0 }}
                  transition={{ duration: 0.6 }}
                >
                  <motion.span
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.2 }}
                  >
                    Powering Your
                  </motion.span>{" "}
                  <motion.span
                    className="text-blue-400 font-semibold"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.4 }}
                  >
                    Distribution Excellence
                  </motion.span>
                </motion.p>
              )}
            </AnimatePresence>

            {/* Loading Bar */}
            <motion.div
              className="mt-12 w-64 h-1 bg-gray-700 rounded-full overflow-hidden mx-auto"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 1.5 }}
            >
              <motion.div
                className="h-full bg-gradient-to-r from-blue-500 via-purple-500 to-green-500"
                initial={{ width: "0%" }}
                animate={{ width: "100%" }}
                transition={{ duration: 2.5, delay: 1.5 }}
              />
            </motion.div>
          </div>
        </div>

        {/* Radial Glow Effect */}
        <motion.div
          className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] rounded-full"
          style={{
            background:
              "radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%)",
          }}
          animate={{
            scale: [1, 1.2, 1],
            opacity: [0.3, 0.5, 0.3],
          }}
          transition={{
            duration: 3,
            repeat: Infinity,
          }}
        />
      </motion.div>
    </AnimatePresence>
  );
}
