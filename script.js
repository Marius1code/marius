
        // Fonction pour afficher un toast
        function showToast(message, title, type = "success") {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <strong>${title}</strong>
                            <p class="mb-0">${message}</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            const toast = new bootstrap.Toast(document.getElementById(toastId), {
                autohide: true,
                delay: 3000
            });
            toast.show();
            document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }

        // Ajouter au panier
        document.querySelectorAll('.add-to-cart').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: 'action=add_to_cart&id=' + id + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').content
                    })
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('cartCount').textContent = data.count;
                        showToast("Produit ajouté au panier avec succès !", "NexaStore", "success");
                        loadCartContent();
                    })
                    .catch(error => {
                        showToast("Une erreur est survenue. Veuillez réessayer.", "NexaStore", "danger");
                    });
            });
        });

        // Charger le contenu du panier
        function loadCartContent() {
            fetch('?action=get_cart')
                .then(res => res.json())
                .then(panier => {
                    let html = '';
                    if (panier.length > 0) {
                        let total = 0;
                        panier.forEach(p => {
                            const itemTotal = p.prix * p.qty;
                            total += itemTotal;
                            html += `
                                <div class="cart-item d-flex justify-content-between align-items-center">
                                    <div class="cart-item-name">
                                        <h6 class="mb-0">${p.nom}</h6>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn decrease" data-id="${p.id}">-</button>
                                        <input type="text" class="form-control form-control-sm quantity-input" value="${p.qty}" readonly>
                                        <button class="btn btn-sm btn-outline-secondary quantity-btn increase" data-id="${p.id}">+</button>
                                        <span class="cart-item-price">${itemTotal.toLocaleString('fr-FR')} FCFA</span>
                                        <button class="btn btn-sm btn-outline-danger remove" data-id="${p.id}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        html += `
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-bold">Sous-total:</span>
                                    <span class="fw-bold">${total.toLocaleString('fr-FR')} FCFA</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">Total:</span>
                                    <span class="fw-bold text-success fs-5">${total.toLocaleString('fr-FR')} FCFA</span>
                                </div>
                        `;
                    } else {
                        html = `
                            <div class="text-center py-4">
                                <i class="fas fa-cart-arrow-down mb-3" style="font-size: 3rem; color: #ddd;"></i>
                                <p class="text-muted">Votre panier est vide.</p>
                                <p class="small">Ajoutez des produits pour commencer vos achats</p>
                            </div>
                        `;
                    }
                    document.getElementById('cartContent').innerHTML = html;
                    fetch('?action=get_whatsapp_link')
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('cartTotal').innerHTML = data.total > 0 ?
                                `<span class="fs-4">${data.total.toLocaleString('fr-FR')} FCFA</span>` :
                                '';
                            document.getElementById('goWhatsApp').href = data.link;
                        });
                    setTimeout(() => {
                        document.querySelectorAll('.increase').forEach(b => {
                            b.addEventListener('click', () => updateQty(b.dataset.id, 'plus'));
                        });
                        document.querySelectorAll('.decrease').forEach(b => {
                            b.addEventListener('click', () => updateQty(b.dataset.id, 'moins'));
                        });
                        document.querySelectorAll('.remove').forEach(b => {
                            b.addEventListener('click', () => updateQty(b.dataset.id, 'remove'));
                        });
                    }, 300);
                });
        }

        // Mettre à jour la quantité
        function updateQty(id, action) {
            let qtyElement = document.querySelector(`.increase[data-id="${id}"]`)?.previousElementSibling ||
                document.querySelector(`.decrease[data-id="${id}"]`)?.nextElementSibling;
            let qty = 1;
            if (action === 'plus') {
                qty = parseInt(qtyElement.value) + 1;
            } else if (action === 'moins') {
                qty = Math.max(1, parseInt(qtyElement.value) - 1);
            }
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: 'action=' + (action === 'remove' ? 'remove' : 'update_qty') + '&id=' + id + '&qty=' + qty + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').content
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('cartCount').textContent = data.count;
                    if (action === 'remove') {
                        showToast("Produit retiré du panier.", "NexaStore", "info");
                    }
                    loadCartContent();
                });
        }

        // Charger le contenu du panier au chargement de la modal
        document.getElementById('cartModal').addEventListener('shown.bs.modal', function() {
            loadCartContent();
        });
        // Gestion des étoiles dans le formulaire d'avis
        document.querySelectorAll('.rating-select i').forEach(star => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                document.getElementById('reviewRatingValue').value = value;
                document.querySelectorAll('.rating-select i').forEach((s, i) => {
                    s.classList.toggle('fas', i < value);
                    s.classList.toggle('far', i >= value);
                });
            });
        });

        // Soumission du formulaire d'avis
        document.getElementById('submitReview').addEventListener('click', function() {
            const name = document.getElementById('reviewName').value;
            const rating = document.getElementById('reviewRatingValue').value;
            const message = document.getElementById('reviewMessage').value;
            if (!name || !message) {
                showToast("Veuillez remplir tous les champs.", "Erreur", "danger");
                return;
            }
            // Ici, vous pouvez envoyer les données au serveur via AJAX
            showToast("Merci pour votre avis ! Il sera publié après modération.", "NexaStore", "success");
            document.getElementById('reviewForm').reset();
            document.querySelectorAll('.rating-select i').forEach(s => {
                s.classList.remove('fas');
                s.classList.add('far');
            });
            document.querySelector('.rating-select i:last-child').classList.add('fas');
            document.getElementById('leaveReviewModal').querySelector('.btn-close').click();
        });
    