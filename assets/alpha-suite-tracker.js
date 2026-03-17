(function () {

    function getPostId() {

        if (typeof PI_TRACKER === 'undefined') return 0;

        return PI_TRACKER.post_id || 0;
    }

    function getSession() {

        let session = localStorage.getItem("pi_session_id");

        if (!session) {
            session = crypto.randomUUID();
            localStorage.setItem("pi_session_id", session);
        }

        return session;
    }

    function sendView() {

        const postId = getPostId();

        if (!postId) return;

        fetch(`${PI_TRACKER.endpoint}/tracker`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                post_id: postId,
                session: getSession()
            })
        });

    }

    function sendDuration(seconds) {

        const postId = getPostId();
        if (!postId) return;

        const data = JSON.stringify({
            post_id: postId,
            duration: seconds,
            session: getSession()
        });

        navigator.sendBeacon(
            PI_TRACKER.endpoint + '/tracker/duration',
            new Blob([data], { type: 'application/json' })
        );
    }

    document.addEventListener("DOMContentLoaded", function () {

        sendView();

        let start = Date.now();

        window.addEventListener("beforeunload", function () {

            const seconds = Math.floor((Date.now() - start) / 1000);

            if (seconds > 5) {
                sendDuration(seconds);
            }

        });
        console.log("Alpha Suite: Tracker iniciado")

    });

})();