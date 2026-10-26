/**
 * @file
 * Comportamientos del tema jllamas (equivalen a los componentes de Lovable).
 */
((Drupal, once) => {
  const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Imagen de obra: fundido al terminar de cargar (ArtImg).
   */
  Drupal.behaviors.jllamasImagenes = {
    attach(context) {
      once('jl-img', '.art-img img', context).forEach((img) => {
        const done = () => img.classList.add('is-loaded');
        if (img.complete && img.naturalWidth) {
          done();
        }
        else {
          img.addEventListener('load', done, { once: true });
          img.addEventListener('error', done, { once: true });
        }
      });
    },
  };

  /**
   * Aparición al entrar en pantalla (useReveal).
   */
  Drupal.behaviors.jllamasReveal = {
    attach(context) {
      const items = once('jl-reveal', '.reveal', context);
      if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
      }
      const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('is-visible');
            io.unobserve(e.target);
          }
        });
      }, { rootMargin: '0px 0px -8% 0px' });
      items.forEach((el) => io.observe(el));
    },
  };

  /**
   * Hero de Inicio: cambia de obra cada 6 s con fundido de 2 s.
   */
  Drupal.behaviors.jllamasHero = {
    attach(context) {
      once('jl-hero', '[data-hero]', context).forEach((hero) => {
        const imgs = hero.querySelectorAll('[data-hero-img]');
        const datos = JSON.parse(hero.querySelector('[data-hero-datos]')?.textContent || '[]');
        const pie = hero.querySelector('[data-hero-pie]');
        if (imgs.length < 2 || reduceMotion()) {
          return;
        }
        let i = 0;
        const altText = Array.from(imgs, (img) => img.getAttribute('alt'));
        const mostrar = (k) => {
          imgs.forEach((img, n) => {
            img.classList.toggle('opacity-100', n === k);
            img.classList.toggle('opacity-0', n !== k);
            img.setAttribute('alt', n === k ? altText[n] : '');
            img.setAttribute('aria-hidden', n === k ? 'false' : 'true');
            if (n === k) {
              img.loading = 'eager';
            }
          });
          if (datos[k]) {
            hero.href = datos[k].url;
            hero.setAttribute('aria-label', `Ver ${datos[k].titulo}`);
            if (pie) {
              pie.textContent = `${datos[k].titulo} · ${datos[k].categoria}`;
            }
          }
        };
        mostrar(0);
        // Precarga la siguiente para que el fundido no muestre un hueco.
        setInterval(() => {
          i = (i + 1) % imgs.length;
          imgs[(i + 1) % imgs.length].loading = 'eager';
          mostrar(i);
        }, 6000);
      });
    },
  };

  /**
   * Fila de destacadas: arrastre con el ratón (DragRow).
   */
  Drupal.behaviors.jllamasArrastre = {
    attach(context) {
      once('jl-arrastre', '[data-arrastre]', context).forEach((row) => {
        const d = { down: false, x: 0, left: 0, moved: false };
        row.addEventListener('pointerdown', (e) => {
          if (e.pointerType !== 'mouse') {
            return;
          }
          Object.assign(d, { down: true, x: e.clientX, left: row.scrollLeft, moved: false });
        });
        row.addEventListener('pointermove', (e) => {
          if (!d.down) {
            return;
          }
          const dx = e.clientX - d.x;
          if (Math.abs(dx) > 4) {
            d.moved = true;
          }
          row.scrollLeft = d.left - dx;
        });
        ['pointerup', 'pointerleave'].forEach((ev) => row.addEventListener(ev, () => { d.down = false; }));
        row.addEventListener('click', (e) => {
          if (d.moved) {
            e.preventDefault();
            e.stopPropagation();
            d.moved = false;
          }
        }, true);
        row.addEventListener('dragstart', (e) => e.preventDefault());
      });
    },
  };

  /**
   * Menú de móvil a pantalla completa.
   */
  Drupal.behaviors.jllamasMenu = {
    attach(context) {
      once('jl-menu', '[data-menu-abrir]', context).forEach((boton) => {
        const panel = document.querySelector('[data-menu]');
        if (!panel) {
          return;
        }
        const cerrar = () => {
          panel.hidden = true;
          document.body.style.overflow = '';
          boton.setAttribute('aria-expanded', 'false');
          boton.focus();
        };
        boton.addEventListener('click', () => {
          panel.hidden = false;
          document.body.style.overflow = 'hidden';
          boton.setAttribute('aria-expanded', 'true');
          panel.querySelector('[data-menu-cerrar]')?.focus();
        });
        panel.querySelector('[data-menu-cerrar]')?.addEventListener('click', cerrar);
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && !panel.hidden) {
            cerrar();
          }
        });
      });
    },
  };

  /**
   * Visor a pantalla completa con zoom (Viewer).
   */
  Drupal.behaviors.jllamasVisor = {
    attach(context) {
      once('jl-visor', '[data-visor]', context).forEach((boton) => {
        boton.addEventListener('click', () => {
          const titulo = boton.dataset.visorTitulo || '';
          const alt = boton.querySelector('img')?.getAttribute('alt') || titulo;
          const visor = document.createElement('div');
          visor.className = 'animate-fade fixed inset-0 z-50 flex flex-col bg-viewer text-viewer-foreground';
          visor.setAttribute('role', 'dialog');
          visor.setAttribute('aria-modal', 'true');
          visor.setAttribute('aria-label', `Visor: ${titulo}`);
          visor.innerHTML = `
            <div class="flex items-center justify-between px-5 py-4">
              <p class="font-display text-lg"></p>
              <div class="flex gap-4">
                <button type="button" data-zoom aria-label="Ampliar">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>
                </button>
                <button type="button" data-cerrar aria-label="Cerrar visor">
                  <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
            <div class="relative flex flex-1 cursor-zoom-in items-center justify-center overflow-hidden p-4 md:p-10" data-lienzo>
              <img class="animate-viewer-in max-h-full max-w-full object-contain transition-transform duration-300" alt="">
            </div>`;
          visor.querySelector('p').textContent = titulo;
          const img = visor.querySelector('img');
          img.src = boton.dataset.visor;
          img.alt = alt;
          const lienzo = visor.querySelector('[data-lienzo]');
          const zoomBtn = visor.querySelector('[data-zoom]');
          let zoom = false;
          const origen = (e) => {
            const r = lienzo.getBoundingClientRect();
            img.style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
          };
          const setZoom = (z) => {
            zoom = z;
            img.style.transform = zoom ? 'scale(2.4)' : 'none';
            lienzo.classList.toggle('cursor-zoom-out', zoom);
            lienzo.classList.toggle('cursor-zoom-in', !zoom);
            zoomBtn.setAttribute('aria-label', zoom ? 'Reducir' : 'Ampliar');
          };
          lienzo.addEventListener('click', (e) => { origen(e); setZoom(!zoom); });
          lienzo.addEventListener('pointermove', (e) => { if (zoom) { origen(e); } });
          zoomBtn.addEventListener('click', () => setZoom(!zoom));
          const cerrar = () => {
            visor.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onKey);
            boton.focus();
          };
          const onKey = (e) => { if (e.key === 'Escape') { cerrar(); } };
          visor.querySelector('[data-cerrar]').addEventListener('click', cerrar);
          document.addEventListener('keydown', onKey);
          document.body.style.overflow = 'hidden';
          document.body.appendChild(visor);
          visor.querySelector('[data-cerrar]').focus();
        });
      });
    },
  };

  /**
   * Filtros de las secciones en el navegador (SectionPage): recuentos en vivo
   * y URL con ?tecnica=&tematica= sin recargar la página.
   */
  Drupal.behaviors.jllamasFiltros = {
    attach(context) {
      once('jl-filtros', '[data-seccion]', context).forEach((seccion) => {
        const obras = Array.from(seccion.querySelectorAll('[data-obra]'));
        const grupos = Array.from(seccion.querySelectorAll('[data-grupo]'));
        const total = seccion.querySelector('[data-total]');
        const vacio = seccion.querySelector('[data-vacio]');
        const base = seccion.dataset.url;

        const valoresDe = (obra, campo) => (campo === 'categoria'
          ? [obra.dataset.categoria]
          : (obra.dataset.tematica || '').split(' ').filter(Boolean));
        const cumple = (obra, activos) => grupos.every((g) => {
          const v = activos[g.dataset.grupo];
          return !v || valoresDe(obra, g.dataset.campo).includes(v);
        });
        const leer = () => {
          const params = new URLSearchParams(window.location.search);
          const activos = {};
          grupos.forEach((g) => {
            const v = params.get(g.dataset.grupo);
            if (v && g.querySelector(`[data-valor="${CSS.escape(v)}"]`)) {
              activos[g.dataset.grupo] = v;
            }
          });
          return activos;
        };
        const pintar = (activos) => {
          let n = 0;
          obras.forEach((obra) => {
            const ok = cumple(obra, activos);
            obra.hidden = !ok;
            if (ok) {
              n += 1;
              obra.classList.add('is-visible');
            }
          });
          if (total) {
            total.textContent = n;
          }
          if (vacio) {
            vacio.hidden = n > 0;
          }
          grupos.forEach((g) => {
            const clave = g.dataset.grupo;
            g.querySelectorAll('[data-valor]').forEach((chip) => {
              const v = chip.dataset.valor;
              const activa = v === '' ? !activos[clave] : activos[clave] === v;
              chip.classList.toggle('is-active', activa);
              chip.setAttribute('aria-pressed', activa ? 'true' : 'false');
              if (v !== '') {
                const prueba = { ...activos, [clave]: v };
                const c = obras.filter((o) => cumple(o, prueba)).length;
                const r = chip.querySelector('[data-recuento]');
                if (r) {
                  r.textContent = c;
                }
                chip.classList.toggle('is-empty', c === 0);
              }
            });
          });
        };
        const url = (activos) => {
          const q = new URLSearchParams(activos).toString();
          return q ? `${base}?${q}` : base;
        };

        grupos.forEach((g) => {
          g.addEventListener('click', (e) => {
            const chip = e.target.closest('[data-valor]');
            if (!chip) {
              return;
            }
            e.preventDefault();
            const activos = leer();
            const clave = g.dataset.grupo;
            const v = chip.dataset.valor;
            if (v === '' || activos[clave] === v) {
              delete activos[clave];
            }
            else {
              activos[clave] = v;
            }
            window.history.pushState(activos, '', url(activos));
            pintar(activos);
          });
        });
        window.addEventListener('popstate', () => pintar(leer()));
      });
    },
  };
})(Drupal, once);
