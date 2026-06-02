"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const navToggle = document.querySelector(".nav-toggle");
  const navLinks = document.querySelector(".nav-links");

  if (navToggle && navLinks) {
    navToggle.addEventListener("click", () => {
      navToggle.classList.toggle("is-open");
      navLinks.classList.toggle("is-open");
    });
    navLinks.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        navToggle.classList.remove("is-open");
        navLinks.classList.remove("is-open");
      });
    });
  }

  const starfield = document.querySelector(".starfield");
  if (starfield) {
    const total = window.matchMedia("(max-width: 768px)").matches ? 18 : 40;
    const fragment = document.createDocumentFragment();
    for (let i = 0; i < total; i += 1) {
      const star = document.createElement("span");
      star.className = "star";
      star.style.setProperty("--x", `${Math.random() * 100}%`);
      star.style.setProperty("--y", `${Math.random() * 100}%`);
      star.style.animationDuration = `${8 + Math.random() * 8}s`;
      star.style.animationDelay = `${Math.random() * 4}s`;
      fragment.appendChild(star);
    }
    starfield.appendChild(fragment);
  }

  const hero = document.querySelector(".hero");
  const heroVisual = document.querySelector(".hero-visual");
  if (hero && heroVisual) {
    const handleMove = (event) => {
      const rect = heroVisual.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - 0.5;
      const y = (event.clientY - rect.top) / rect.height - 0.5;
      heroVisual.style.transform = `perspective(1200px) rotateX(${y * 8}deg) rotateY(${x * -8}deg)`;
    };
    heroVisual.addEventListener("pointermove", handleMove);
    heroVisual.addEventListener("pointerleave", () => {
      heroVisual.style.transform = "perspective(1200px) rotateX(0deg) rotateY(0deg)";
    });
  }
});
