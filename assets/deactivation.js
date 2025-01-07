window.mktr = window.mktr || {};
window.dataLayer = window.dataLayer || [];
window.mktr.addListeners = false;
window.mktr.send = true;
window.mktr.deactivate = true;

window.mktr.feedback_button = "#deactivate-themarketer,#deactivate-themarketer-woocommerce-version,[href*='plugins.php?action=deactivate&plugin=themarketer']";
window.mktr.feedback_close = ".mktr-modal-feedback-close";
window.mktr.feedback_submit = '.mktr-modal-feedback-submit';
window.mktr.feedback_activate = '.mktr-modal-feedback';
window.mktr.feedback_deactivate = ".mktr-modal-feedback-deactivate";


window.mktr.rating = '.mktr-rating span';
window.mktr.rating_button = '.mktr-modal-rate-active';
window.mktr.rating_close = '.mktr-modal-rate-close';
window.mktr.rating_activate = '.mktr-modal-rate';
window.mktr.rating_submit = '.mktr-modal-rate-submit';

window.mktr.changeLocation = function () {
    if (window.mktr.deactivate) {
        window.mktr.deactivate = false;
        location.href = document.querySelectorAll(window.mktr.feedback_button)[0].href;
    }
};

document.addEventListener("click", function(event){
    if (event.target.matches(window.mktr.feedback_close) || event.target.closest(window.mktr.feedback_close)) {
        document.querySelector(window.mktr.feedback_activate).style.display = "none";
    } else if (event.target.matches(window.mktr.rating_close) || event.target.closest(window.mktr.rating_close)) {
        document.querySelector(window.mktr.rating_activate).style.display = "none";
    } else if (event.target.matches(window.mktr.feedback_deactivate) || event.target.closest(window.mktr.feedback_deactivate)) {
        document.querySelector(window.mktr.feedback_activate).style.display = "none";
        window.mktr.changeLocation();
    } else if (event.target.matches(window.mktr.rating_button) || event.target.closest(window.mktr.rating_button)){
        window.mktr.modal = document.querySelector(window.mktr.rating_activate);
        if (window.mktr.modal !== null) {
            event.preventDefault();
            window.mktr.modal.style.display = "block";
        }
    } else if (event.target.matches(window.mktr.feedback_button) || event.target.closest(window.mktr.feedback_button)){
        window.mktr.modal = document.querySelector(window.mktr.feedback_activate);
        if (window.mktr.modal !== null) {
            event.preventDefault();
            window.mktr.modal.style.display = "block";
        }
    } else if (event.target.matches(window.mktr.feedback_submit) || event.target.closest(window.mktr.feedback_submit)) {
        document.querySelector(window.mktr.feedback_activate).style.display = "none";
        
        if (window.mktr.send) {
            window.mktr.send = false;
            try {
                let formData = new FormData();

                formData.append('message', document.querySelector('#mktr_message').value);

                fetch(mktr_data.url + '?mktr=FeedBack', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { window.mktr.send = true; window.mktr.changeLocation(); })
                .catch((error) => { window.mktr.send = true; });

            } catch (error) {
                console.info(error);
            }
        }
        setTimeout(window.mktr.changeLocation, 5000);
    } else if (event.target.matches(window.mktr.rating_submit) || event.target.closest(window.mktr.rating_submit)) {
        document.querySelectorAll(window.mktr.rating_activate).forEach(function(item){ item.style.display = "none"; });
        document.querySelectorAll(window.mktr.rating_button).forEach(function(item){ item.style.display = "none"; });

        if (window.mktr.send) {
            window.mktr.send = false;
            try {
                let formData = new FormData();

                formData.append('rating', document.querySelector('#mktr-rating-value').value);
                formData.append('message', document.querySelector('#mktr-rating-message').value);
                
                fetch(mktr_data.url + '?mktr=FeedBack', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    window.mktr.send = true; 
                })
                .catch((error) => { window.mktr.send = true; });
                
            } catch (error) {
                console.info(error);
            }
        }
    } else if (event.target.closest(window.mktr.rating) || event.target.matches(window.mktr.rating)) {
        document.querySelector('.mktr-rating').classList.add('active');
        document.querySelector('#mktr-rating-value').value = event.target.getAttribute('rate');
        document.querySelectorAll('.mktr-rating span').forEach(function(span) { span.classList.remove('active'); });

        event.target.classList.add('active');

        if (event.target.getAttribute('rate') < 5) {
            document.querySelector(window.mktr.rating_activate + ' .mktr-modal-rate-message').style.display = "block";
        } else {
            document.querySelectorAll(window.mktr.rating_activate).forEach(function(item){ item.style.display = "none"; });
            document.querySelectorAll(window.mktr.rating_button).forEach(function(item){ item.style.display = "none"; });

            if (window.mktr.send) {
                window.mktr.send = false;
                try {
                    let formData = new FormData();

                    formData.append('rating', document.querySelector('#mktr-rating-value').value);
                    
                    fetch(mktr_data.url + '?mktr=FeedBack', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        window.mktr.send = true;
                        window.open('https://wordpress.org/support/plugin/themarketer/reviews/', '_blank');
                    })
                    .catch((error) => { window.mktr.send = true; });
                    
                } catch (error) {
                    console.info(error);
                }
            }
        }
    }
});