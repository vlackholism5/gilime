(function () {
  var STORAGE_KEY = 'gilaime_admin_tutorial_hide_until';
  var MODAL_ID = 'gilaime-admin-tutorial-modal';

  function todayString() {
    var d = new Date();
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function shouldShowTutorial() {
    try {
      var hideUntil = localStorage.getItem(STORAGE_KEY);
      if (!hideUntil) return true;
      var today = todayString();
      return today > hideUntil;
    } catch (e) {
      return true;
    }
  }

  function setHideUntilToday() {
    try {
      localStorage.setItem(STORAGE_KEY, todayString());
    } catch (e) {}
  }

  function run() {
    var el = document.getElementById(MODAL_ID);
    if (!el) return;
    var modal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(el) : null;
    if (!modal) return;

    el.addEventListener('click', function (e) {
      var t = e.target;
      if (!t || !t.id) return;
      if (t.id === 'gilaime-admin-tutorial-skip') {
        e.preventDefault();
        modal.hide();
        return;
      }
      if (t.id === 'gilaime-admin-tutorial-hide-today') {
        e.preventDefault();
        setHideUntilToday();
        modal.hide();
        return;
      }
    });

    if (shouldShowTutorial()) {
      modal.show();
    }
  }

  /** 관리자 테스트/다시 보기: 모달만 띄우기 (localStorage 변경 없음) */
  window.gilaimeShowTutorialModal = function () {
    var el = document.getElementById(MODAL_ID);
    if (!el) return;
    var modal = window.bootstrap && window.bootstrap.Modal ? (window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el)) : null;
    if (modal) modal.show();
  };

  /** 관리자 테스트: 오늘 다시 보지 않기 저장값 삭제 */
  window.gilaimeClearTutorialHideUntil = function () {
    try {
      localStorage.removeItem(STORAGE_KEY);
      return true;
    } catch (e) {
      return false;
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
