<script>
(function () {
    const page = document.getElementById('supplierOrderShowPage');
    if (!page) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const canCancel = page.dataset.canCancel === '1';
    const cancelUrl = page.dataset.cancelUrl || '';
    const orderNumber = page.dataset.orderNumber || '';
    const backUrl = page.dataset.backUrl || '';

    const alertHost = document.getElementById('orderShowAlertHost');
    const cancelBtn = document.getElementById('orderShowCancelBtn');
    const cancelModalEl = document.getElementById('orderCancelModal');
    const cancelModalCopy = document.getElementById('orderCancelModalCopy');
    const cancelReasonInput = document.getElementById('orderCancelReason');
    const cancelConfirmBtn = document.getElementById('orderCancelConfirmBtn');
    const cancelModal = cancelModalEl && window.bootstrap
        ? new bootstrap.Modal(cancelModalEl)
        : null;

    let submitting = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(type, message) {
        if (!alertHost) {
            return;
        }

        const className = type === 'success'
            ? 'alert alert-success'
            : (type === 'warning' ? 'alert alert-warning' : 'alert alert-danger');

        alertHost.className = className;
        alertHost.textContent = message;
        alertHost.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function openCancelModal() {
        if (!canCancel || !cancelModal) {
            return;
        }

        if (cancelReasonInput) {
            cancelReasonInput.value = '';
        }
        if (cancelModalCopy) {
            cancelModalCopy.innerHTML = `Cancel order <strong>${escapeHtml(orderNumber)}</strong> from the supplier fulfillment queue?`;
        }
        cancelModal.show();
    }

    async function confirmCancel() {
        if (!canCancel || !cancelUrl || submitting) {
            return;
        }

        submitting = true;
        if (cancelConfirmBtn) {
            cancelConfirmBtn.disabled = true;
            cancelConfirmBtn.textContent = 'Cancelling...';
        }

        try {
            const response = await fetch(cancelUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    reason: cancelReasonInput ? cancelReasonInput.value.trim() : '',
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const detail = payload.message
                    || (payload.errors && typeof payload.errors === 'object'
                        ? Object.values(payload.errors).flat().join(' ')
                        : null)
                    || 'Unable to cancel order.';
                throw new Error(detail);
            }

            if (cancelModal) {
                cancelModal.hide();
            }

            showAlert('success', payload.message || 'Order cancelled.');
            window.setTimeout(function () {
                window.location.href = backUrl || '/orders/new';
            }, 700);
        } catch (error) {
            showAlert('error', error.message || 'Unable to cancel order.');
        } finally {
            submitting = false;
            if (cancelConfirmBtn) {
                cancelConfirmBtn.disabled = false;
                cancelConfirmBtn.textContent = 'Cancel order';
            }
        }
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', openCancelModal);
    }

    if (cancelConfirmBtn) {
        cancelConfirmBtn.addEventListener('click', confirmCancel);
    }
})();
</script>
