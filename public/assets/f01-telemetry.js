(function(){
    var root = document.querySelector('meta[name="f01-career-context"]');
    if (!root) {
        var el = document.querySelector('[data-career-id]');
        if (el) {
            triggerDrain(el.getAttribute('data-career-id'), el.getAttribute('data-csrf'));
        }
        return;
    }
    var careerId = root.getAttribute('content');
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    if (careerId && csrf) {
        triggerDrain(careerId, csrf);
    }

    function triggerDrain(cid, ctoken) {
        var fd = new FormData();
        fd.append('career_id', cid);
        fd.append('csrf', ctoken);
        fd.append('limit', '20');
        fetch('/api/v1/telemetry/drain', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        }).catch(function(){});
    }
})();

