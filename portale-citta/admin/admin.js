/* Amministrazione — comportamenti dell'interfaccia. */
(function () {
	'use strict';

	/* --- Linguette ------------------------------------------------------ */
	// La linguetta aperta si ricorda per singola schermata: aprire un'altra
	// pagina riparte sempre dalla prima, non dall'ultima usata altrove.
	var chiaveLinguetta = 'pc-linguetta:' + window.location.search;

	// Da qui in poi le schede si aprono una per volta: senza JavaScript
	// restano tutte aperte, così nessuna resta irraggiungibile.
	document.body.classList.add('pc-js');

	document.querySelectorAll('[data-linguette]').forEach(function (gruppo) {
		var bottoni = gruppo.querySelectorAll('.pc-linguetta');
		bottoni.forEach(function (b) {
			b.addEventListener('click', function () {
				var bersaglio = b.getAttribute('data-pannello');
				bottoni.forEach(function (x) { x.classList.remove('is-attiva'); });
				b.classList.add('is-attiva');
				document.querySelectorAll('.pc-pannello').forEach(function (p) {
					p.classList.toggle('is-attivo', p.id === bersaglio);
				});
				try { sessionStorage.setItem(chiaveLinguetta, bersaglio); } catch (e) {}
			});
		});
		try {
			var salvata = sessionStorage.getItem(chiaveLinguetta);
			if (salvata && document.getElementById(salvata)) {
				var b = gruppo.querySelector('[data-pannello="' + salvata + '"]');
				if (b) { b.click(); }
			}
		} catch (e) {}
	});

	/* --- Campi ripetibili ------------------------------------------------ */
	document.querySelectorAll('[data-ripeti]').forEach(function (contenitore) {
		var nome = contenitore.getAttribute('data-ripeti');
		var modello = document.getElementById('modello-' + nome);
		var aggiungi = document.querySelector('[data-aggiungi="' + nome + '"]');

		function indiceProssimo() {
			return contenitore.querySelectorAll('.pc-ripeti__voce').length;
		}

		if (aggiungi && modello) {
			aggiungi.addEventListener('click', function () {
				var html = modello.innerHTML.replace(/__i__/g, indiceProssimo());
				var div = document.createElement('div');
				div.innerHTML = html;
				var voce = div.firstElementChild;
				contenitore.appendChild(voce);
				var primo = voce.querySelector('input, textarea, select');
				if (primo) { primo.focus(); }
			});
		}

		contenitore.addEventListener('click', function (ev) {
			var togli = ev.target.closest('.pc-ripeti__togli');
			if (!togli) { return; }
			var voce = togli.closest('.pc-ripeti__voce');
			if (voce) { voce.remove(); }
		});
	});

	/* --- Slug automatico dal titolo -------------------------------------- */
	function slugifica(testo) {
		return testo.toLowerCase()
			.normalize('NFD').replace(/[̀-ͯ]/g, '')
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}
	document.querySelectorAll('[data-slug-da]').forEach(function (campoSlug) {
		var origine = document.getElementById(campoSlug.getAttribute('data-slug-da'));
		if (!origine) { return; }
		var toccato = campoSlug.value.trim() !== '';
		campoSlug.addEventListener('input', function () { toccato = true; });
		origine.addEventListener('input', function () {
			if (!toccato) { campoSlug.value = slugifica(origine.value); }
		});
	});

	/* --- Conferma prima di eliminare ------------------------------------- */
	document.querySelectorAll('[data-conferma]').forEach(function (el) {
		el.addEventListener('click', function (ev) {
			if (!window.confirm(el.getAttribute('data-conferma'))) {
				ev.preventDefault();
			}
		});
	});

	/* --- Assistente Gemini ------------------------------------------------ */
	document.querySelectorAll('[data-ai]').forEach(function (bottone) {
		bottone.addEventListener('click', function () {
			var compito = bottone.getAttribute('data-ai');
			var bersaglio = document.getElementById(bottone.getAttribute('data-ai-campo') || '');
			var pagina = bottone.getAttribute('data-ai-pagina') || '';
			var citta = bottone.getAttribute('data-ai-citta') || '';
			var etichetta = bottone.textContent;

			bottone.disabled = true;
			bottone.textContent = 'Scrivo…';

			var corpo = new FormData();
			corpo.append('compito', compito);
			corpo.append('pagina', pagina);
			corpo.append('citta', citta);
			corpo.append('token', document.body.getAttribute('data-token') || '');
			// Il modulo corrente viene inviato così l'assistente vede le modifiche non salvate.
			var modulo = bottone.closest('form');
			if (modulo) {
				new FormData(modulo).forEach(function (v, k) {
					if (k !== 'token') { corpo.append('modulo[' + k + ']', v); }
				});
			}

			fetch('admin.php?p=ai', { method: 'POST', body: corpo })
				.then(function (r) { return r.json(); })
				.then(function (d) {
					bottone.disabled = false;
					bottone.textContent = etichetta;
					if (!d.ok) {
						window.alert('Assistente: ' + (d.errore || 'errore sconosciuto'));
						return;
					}
					if (compito === 'faq') {
						inserisciFaq(d.voci);
						return;
					}
					if (bersaglio) {
						bersaglio.value = d.testo;
						bersaglio.dispatchEvent(new Event('input', { bubbles: true }));
					}
				})
				.catch(function (err) {
					bottone.disabled = false;
					bottone.textContent = etichetta;
					window.alert('Assistente non raggiungibile: ' + err.message);
				});
		});
	});

	function inserisciFaq(voci) {
		var contenitore = document.querySelector('[data-ripeti="faq"]');
		var modello = document.getElementById('modello-faq');
		if (!contenitore || !modello || !voci) { return; }
		voci.forEach(function (v) {
			var i = contenitore.querySelectorAll('.pc-ripeti__voce').length;
			var div = document.createElement('div');
			div.innerHTML = modello.innerHTML.replace(/__i__/g, i);
			var voce = div.firstElementChild;
			var d = voce.querySelector('[name$="[domanda]"]');
			var r = voce.querySelector('[name$="[risposta]"]');
			if (d) { d.value = v.domanda; }
			if (r) { r.value = v.risposta; }
			contenitore.appendChild(voce);
		});
	}

	/* --- Contatore caratteri SEO ------------------------------------------ */
	document.querySelectorAll('[data-conta]').forEach(function (campo) {
		var min = parseInt(campo.getAttribute('data-conta-min') || '0', 10);
		var max = parseInt(campo.getAttribute('data-conta-max') || '0', 10);
		var nota = document.createElement('small');
		campo.parentNode.appendChild(nota);
		function aggiorna() {
			var n = campo.value.length;
			var stato = (n >= min && n <= max) ? '✓' : '!';
			nota.textContent = stato + ' ' + n + ' caratteri (ideale ' + min + '-' + max + ')';
			nota.style.color = (n >= min && n <= max) ? '#0f7b3f' : '#b3261e';
		}
		campo.addEventListener('input', aggiorna);
		aggiorna();
	});
})();

/* Caricamento dei modelli Gemini disponibili per la chiave inserita. */
(function () {
	'use strict';

	var bottone = document.getElementById('carica-modelli');
	if (!bottone) { return; }

	var esito = document.getElementById('esito-modelli');
	var campo = document.getElementById('campo-modello');
	var elenco = document.getElementById('elenco-modelli');

	function messaggio(testo, colore) {
		esito.textContent = testo;
		esito.style.color = colore;
	}

	bottone.addEventListener('click', function () {
		var etichetta = bottone.textContent;
		bottone.disabled = true;
		bottone.textContent = 'Chiedo a Google…';
		messaggio('', '');

		var corpo = new FormData();
		corpo.append('compito', 'modelli');
		corpo.append('token', bottone.getAttribute('data-token') || '');

		fetch('admin.php?p=ai', { method: 'POST', body: corpo })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				bottone.disabled = false;
				bottone.textContent = etichetta;

				if (!d.ok) {
					messaggio('✗ ' + (d.errore || 'errore sconosciuto'), '#b3261e');
					return;
				}
				if (!d.modelli || !d.modelli.length) {
					messaggio('La chiave funziona, ma non risulta nessun modello utilizzabile.', '#b3261e');
					return;
				}

				var campoImg = document.getElementById('campo-modello-immagini');
				var elencoImg = document.getElementById('elenco-modelli-immagini');
				elenco.innerHTML = '';
				if (elencoImg) { elencoImg.innerHTML = ''; }

				// I modelli che sanno disegnare hanno "image" nel nome.
				var disegnano = d.modelli.filter(function (m) { return m.nome.toLowerCase().indexOf('image') !== -1; });
				var scrivono = d.modelli.filter(function (m) { return m.nome.toLowerCase().indexOf('image') === -1; });

				function riempi(lista, dove) {
					if (!dove) { return; }
					lista.forEach(function (m) {
						var o = document.createElement('option');
						o.value = m.nome;
						o.label = m.etichetta;
						dove.appendChild(o);
					});
				}
				riempi(scrivono, elenco);
				riempi(disegnano, elencoImg);

				// Ogni campo si corregge con un modello del suo tipo, mai dell'altro.
				var corretti = [];

				function sistema(input, lista, tipo) {
					if (!input || !lista.length) { return; }
					var nomi = lista.map(function (m) { return m.nome; });
					if (nomi.indexOf(input.value.trim()) === -1) {
						input.value = nomi[0];
						corretti.push(tipo + ' → ' + nomi[0]);
					}
				}
				sistema(campo, scrivono, 'testo');
				sistema(campoImg, disegnano, 'immagini');

				var quanti = scrivono.length + ' per il testo, ' + disegnano.length + ' per le immagini';

				if (corretti.length) {
					messaggio('✓ Chiave valida: ' + quanti + '. Ho corretto ciò che non era più valido ('
						+ corretti.join('; ') + '). Salva per confermare.', '#b3261e');
				} else {
					messaggio('✓ Chiave valida: ' + quanti
						+ '. I campi ora li suggeriscono mentre scrivi.', '#0f7b3f');
				}
			})
			.catch(function (err) {
				bottone.disabled = false;
				bottone.textContent = etichetta;
				messaggio('✗ Non sono riuscito a contattare il server: ' + err.message, '#b3261e');
			});
	});
})();

/* Generazione dell'immagine di anteprima. */
(function () {
	'use strict';

	var bottone = document.getElementById('genera-immagine');
	if (!bottone) { return; }

	var esito = document.getElementById('esito-immagine');
	var campo = document.getElementById('campo-immagine');
	var richiesta = document.getElementById('campo-richiesta-immagine');
	var anteprima = document.getElementById('anteprima-immagine');
	var anteprimaImg = document.getElementById('anteprima-immagine-img');

	function messaggio(testo, colore) {
		esito.textContent = testo;
		esito.style.color = colore;
	}

	bottone.addEventListener('click', function () {
		var etichetta = bottone.textContent;
		bottone.disabled = true;
		messaggio('Sto disegnando… può volerci mezzo minuto.', '#6b6b6b');

		// Un contatore, altrimenti sembra bloccato.
		var secondi = 0;
		var tic = window.setInterval(function () {
			secondi++;
			bottone.textContent = 'Disegno… ' + secondi + 's';
		}, 1000);

		function finito() {
			window.clearInterval(tic);
			bottone.disabled = false;
			bottone.textContent = etichetta;
		}

		var corpo = new FormData();
		corpo.append('compito', 'immagine');
		corpo.append('pagina', bottone.getAttribute('data-ai-pagina') || '');
		corpo.append('citta', bottone.getAttribute('data-ai-citta') || '');
		corpo.append('richiesta', richiesta ? richiesta.value : '');
		corpo.append('token', document.body.getAttribute('data-token') || '');

		fetch('admin.php?p=ai', { method: 'POST', body: corpo })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				finito();
				if (!d.ok) {
					messaggio('✗ ' + (d.errore || 'errore sconosciuto'), '#b3261e');
					return;
				}

				// L'immagine è già salvata nella libreria: la si aggiunge all'elenco.
				var esistente = Array.prototype.slice.call(campo.options)
					.some(function (o) { return o.value === d.file; });
				if (!esistente) {
					var o = document.createElement('option');
					o.value = d.file;
					o.textContent = d.file;
					campo.appendChild(o);
				}
				campo.value = d.file;

				if (anteprimaImg) {
					anteprimaImg.src = d.url;
					anteprimaImg.alt = d.alt || '';
				}
				if (anteprima) { anteprima.style.display = ''; }

				messaggio('✓ Immagine creata e selezionata (' + d.file + '). Salva la pagina per confermare.', '#0f7b3f');
			})
			.catch(function (err) {
				finito();
				messaggio('✗ Non sono riuscito a contattare il server: ' + err.message, '#b3261e');
			});
	});
})();

/* Shortcode di una sezione: cambia insieme alla tendina, senza ricaricare. */
(function () {
	'use strict';

	var scelta = document.getElementById('scelta-sezione');
	var campo = document.getElementById('shortcode-sezione');
	if (!scelta || !campo) { return; }

	scelta.addEventListener('change', function () {
		var modello = campo.getAttribute('data-modello') || '';
		campo.value = modello.replace('__SEZIONE__', scelta.value);
		campo.title = campo.value;
	});
})();

/* Campi da copiare: un clic prende tutto, e lo dice. */
(function () {
	'use strict';

	document.querySelectorAll('input[readonly].pc-mono, #shortcode-sezione').forEach(function (campo) {
		campo.addEventListener('click', function () {
			campo.select();
			// Su http la scrittura negli appunti non è permessa: resta
			// selezionato, e si copia a mano. Nessun errore in console.
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(campo.value).then(function () {
					var prima = campo.style.borderColor;
					campo.style.borderColor = '#0a0';
					setTimeout(function () { campo.style.borderColor = prima; }, 700);
				}, function () {});
			}
		});
	});
})();

/* Liste spostabili: le voci del menu di una città e le sezioni di una
   pagina. I pulsanti restano invii veri, quindi senza JavaScript le due
   schermate funzionano lo stesso — solo con un ricaricamento in mezzo. */
(function () {
	'use strict';

	function collega(cfg) {
		var zona = document.getElementById(cfg.zona);
		var dentro = document.getElementById(cfg.dentro);
		var fuori = document.getElementById(cfg.fuori);
		if (!zona || !dentro || !fuori) { return; }

		var anteprima = cfg.anteprima ? document.getElementById(cfg.anteprima) : null;

		function conta(id, n) {
			var e = document.getElementById(id);
			if (e) { e.textContent = '(' + n + ')'; }
		}

		function aggiorna() {
			var voci = dentro.querySelectorAll('.pc-voce');
			var quanteFuori = fuori.querySelectorAll('.pc-voce').length;

			// Numeri progressivi e frecce spente ai due capi.
			voci.forEach(function (v, i) {
				var num = v.querySelector('.pc-voce__num');
				if (num) { num.textContent = i + 1; }
				var su = v.querySelector('[value^="su:"]');
				var giu = v.querySelector('[value^="giu:"]');
				if (su) { su.disabled = i === 0; }
				if (giu) { giu.disabled = i === voci.length - 1; }
			});

			conta(cfg.contaDentro, voci.length);
			conta(cfg.contaFuori, quanteFuori);

			var vd = document.getElementById(cfg.vuotoDentro);
			var vf = document.getElementById(cfg.vuotoFuori);
			if (vd) { vd.style.display = voci.length ? 'none' : ''; }
			if (vf) { vf.style.display = quanteFuori ? 'none' : ''; }

			// Anteprima della barra: solo il menu ce l'ha.
			if (anteprima) {
				anteprima.innerHTML = '';
				var larghezza = 0;
				voci.forEach(function (v) {
					var t = v.getAttribute('data-titolo') || '';
					var s = document.createElement('span');
					s.className = 'pc-barra-finta__voce';
					s.textContent = t;
					anteprima.appendChild(s);
					larghezza += t.length * 7 + 26;
				});
				var avviso = document.getElementById('avviso-larghezza');
				if (avviso) { avviso.style.display = larghezza > 820 ? '' : 'none'; }
			}
		}

		function campo(voce, id) {
			var h = voce.querySelector('input[name="' + cfg.campo + '"]');
			if (!h) {
				h = document.createElement('input');
				h.type = 'hidden';
				h.name = cfg.campo;
				h.value = id;
				voce.insertBefore(h, voce.firstChild);
			}
			return h;
		}

		function bottoni(voce, id, verso) {
			var azioni = voce.querySelector('.pc-voce__azioni');
			if (!azioni) { return; }
			azioni.innerHTML = '';

			function crea(valore, classe, segno, titolo) {
				var b = document.createElement('button');
				b.type = 'submit';
				b.name = cfg.bottone;
				b.value = valore + ':' + id;
				b.className = 'pc-tondo' + (classe ? ' ' + classe : '');
				b.textContent = segno;
				b.title = titolo;
				b.setAttribute('aria-label', titolo);
				azioni.appendChild(b);
			}

			if ('dentro' === verso) {
				crea('su', '', '↑', 'Sposta su');
				crea('giu', '', '↓', 'Sposta giù');
				crea('fuori', 'pc-tondo--rosso', '×', cfg.togli);
			} else {
				crea('dentro', 'pc-tondo--verde', '+', cfg.metti);
			}
		}

		zona.addEventListener('click', function (ev) {
			var b = ev.target.closest('[name="' + cfg.bottone + '"]');
			if (!b) { return; }
			ev.preventDefault();

			var parti = b.value.split(':');
			var cosa = parti[0];
			var id = parti[1];
			var voce = b.closest('.pc-voce');
			if (!voce) { return; }

			if ('su' === cosa && voce.previousElementSibling) {
				dentro.insertBefore(voce, voce.previousElementSibling);
			} else if ('giu' === cosa && voce.nextElementSibling) {
				dentro.insertBefore(voce.nextElementSibling, voce);
			} else if ('fuori' === cosa) {
				var h = voce.querySelector('input[name="' + cfg.campo + '"]');
				if (h) { h.remove(); }
				var num = voce.querySelector('.pc-voce__num');
				if (num) { num.remove(); }
				bottoni(voce, id, 'fuori');
				fuori.appendChild(voce);
			} else if ('dentro' === cosa) {
				if (!voce.querySelector('.pc-voce__num')) {
					var n = document.createElement('span');
					n.className = 'pc-voce__num';
					voce.insertBefore(n, voce.firstChild);
				}
				campo(voce, id);
				bottoni(voce, id, 'dentro');
				dentro.appendChild(voce);
			}

			aggiorna();
		});

		aggiorna();
	}

	collega({
		zona: 'modulo-menu',
		dentro: 'lista-dentro', fuori: 'lista-fuori',
		campo: 'dentro[]', bottone: 'muovi',
		contaDentro: 'conta-dentro', contaFuori: 'conta-fuori',
		vuotoDentro: 'vuoto-dentro', vuotoFuori: 'vuoto-fuori',
		anteprima: 'anteprima-menu',
		togli: 'Togli dal menu', metti: 'Metti nel menu'
	});

	collega({
		zona: 'modulo-sezioni',
		dentro: 'lista-sez-dentro', fuori: 'lista-sez-fuori',
		campo: 'sezioni[]', bottone: 'muovi_sezione',
		contaDentro: 'conta-sez-dentro', contaFuori: 'conta-sez-fuori',
		vuotoDentro: 'vuoto-sez-dentro', vuotoFuori: 'vuoto-sez-fuori',
		togli: 'Togli dalla pagina', metti: 'Metti nella pagina'
	});
})();
