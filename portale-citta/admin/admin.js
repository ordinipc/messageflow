/* Amministrazione — comportamenti dell'interfaccia. */
(function () {
	'use strict';

	/* --- Linguette ------------------------------------------------------ */
	// La linguetta aperta si ricorda per singola schermata: aprire un'altra
	// pagina riparte sempre dalla prima, non dall'ultima usata altrove.
	var chiaveLinguetta = 'pc-linguetta:' + window.location.search;

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

				elenco.innerHTML = '';
				d.modelli.forEach(function (m) {
					var o = document.createElement('option');
					o.value = m.nome;
					o.label = m.etichetta;
					elenco.appendChild(o);
				});

				// Se il modello impostato non è fra quelli validi, si propone il primo.
				var validi = d.modelli.map(function (m) { return m.nome; });
				if (validi.indexOf(campo.value.trim()) === -1) {
					var primo = validi[0];
					campo.value = primo;
					messaggio('✓ ' + validi.length + ' modelli disponibili. Quello impostato non era più valido: ho messo "'
						+ primo + '". Salva per confermare.', '#b3261e');
				} else {
					messaggio('✓ Chiave valida, ' + validi.length
						+ ' modelli disponibili. Il campo ora li suggerisce mentre scrivi.', '#0f7b3f');
				}
			})
			.catch(function (err) {
				bottone.disabled = false;
				bottone.textContent = etichetta;
				messaggio('✗ Non sono riuscito a contattare il server: ' + err.message, '#b3261e');
			});
	});
})();
