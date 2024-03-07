window.mktr = window.mktr || {};
window.mktr.addListeners = false;
window.mktr.send = true;
window.mktr.deactivate = true;
window.mktr.button = "#deactivate-themarketer,#deactivate-themarketer-woocommerce-version,[href*='plugins.php?action=deactivate&plugin=themarketer']";
window.mktr.changeLocation = function () {
    if (window.mktr.deactivate) {
        window.mktr.deactivate = false;
        location.href = document.querySelectorAll(window.mktr.button)[0].href;
    }
};

document.addEventListener("click", function(event){
    if (event.target.matches(window.mktr.button) || event.target.closest(window.mktr.button)){
        window.mktr.modal = document.querySelector('.mktr-modal');
        if (window.mktr.modal !== null) {
            event.preventDefault();
            window.mktr.modal.style.display = "block";
        }
    } else if (event.target.matches(".mk-action-close") || event.target.closest(".mk-action-close")) {
        window.mktr.modal = document.querySelector('.mktr-modal');
        window.mktr.modal.style.display = "none";
    } else if (event.target.matches(".mk-action-submit") || event.target.closest(".mk-action-submit")) {
        window.mktr.modal = document.querySelector('.mktr-modal');
        window.mktr.modal.style.display = "none";

        if (window.mktr.send) {
            window.mktr.send = false;
            try {
                var d = new FormData();
                d.append('message', document.querySelector('#mktr_message').value);
                fetch(mktr_data.url + '?mktr=FeedBack', { method: 'POST', body: d })
                .then(response => response.json()).then(data => { window.mktr.send = true; window.mktr.changeLocation(); })
                .catch((error) => { window.mktr.send = true; });
            } catch (error) {
                console.info(error);
            }
        }
        setTimeout(window.mktr.changeLocation, 5000);
        
    } else if (event.target.matches(".mk-action-deactivate") || event.target.closest(".mk-action-deactivate")) {
        window.mktr.modal = document.querySelector('.mktr-modal');
        window.mktr.modal.style.display = "none";
        window.mktr.changeLocation();
    }
});