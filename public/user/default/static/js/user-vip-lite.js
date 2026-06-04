(function () {
  var message = document.getElementById('vipMessage');
  var modal = document.getElementById('vipConfirm');
  var modalText = document.getElementById('vipConfirmText');
  var confirmButton = document.querySelector('[data-vip-confirm]');
  var cancelButton = document.querySelector('[data-vip-cancel]');
  var activePlan = null;

  function showMessage(text, type, showRecharge) {
    if (!message) return;
    message.className = 'vip-message ' + (type === 'success' ? 'success' : 'error');
    message.textContent = text || '';

    if (showRecharge) {
      var link = document.createElement('a');
      link.href = '/Deal/Recharge';
      link.textContent = '前往充值';
      message.appendChild(link);
    }

    message.hidden = !text;
  }

  function openModal(plan) {
    activePlan = plan;
    if (modalText) {
      modalText.textContent = '确认购买 "' + plan.name + '" 套餐，账户余额将扣除 ¥' + plan.price + '。';
    }
    if (modal) {
      modal.hidden = false;
    }
  }

  function closeModal() {
    activePlan = null;
    if (modal) {
      modal.hidden = true;
    }
  }

  function setLoading(loading) {
    if (!confirmButton) return;
    confirmButton.disabled = loading;
    confirmButton.textContent = loading ? '处理中...' : '确认购买';
  }

  document.querySelectorAll('[data-buy-vip]').forEach(function (button) {
    button.addEventListener('click', function () {
      showMessage('', 'error');
      openModal({
        id: button.getAttribute('data-plan-id'),
        name: button.getAttribute('data-plan-name') || '会员',
        price: button.getAttribute('data-plan-price') || '0.00'
      });
    });
  });

  if (cancelButton) {
    cancelButton.addEventListener('click', closeModal);
  }

  if (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal();
      }
    });
  }

  if (confirmButton) {
    confirmButton.addEventListener('click', function () {
      if (!activePlan || !activePlan.id) return;
      setLoading(true);

      fetch('/Deal/Vip', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams({ tcid: activePlan.id }).toString()
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('请求失败');
          }
          return response.json();
        })
        .then(function (res) {
          closeModal();
          if (res.code === 200) {
            showMessage(res.msg || '购买成功', 'success');
            window.setTimeout(function () {
              window.location.reload();
            }, 900);
            return;
          }

          showMessage(res.msg || '购买失败', 'error', res.code === 202);
        })
        .catch(function () {
          closeModal();
          showMessage('请求失败，请稍后重试', 'error');
        })
        .finally(function () {
          setLoading(false);
        });
    });
  }
})();
