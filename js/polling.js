/************************************************************************
 *
 * For CSS purposes it can be very useful to add a 'hasJS' class to the HTML
 * element allowing different styling options when JavaScript is enabled
 *
 *************************************************************************/
if (document.getElementsByTagName('html')[0].className.indexOf("hasJS") == -1) {
    document.getElementsByTagName('html')[0].className += " hasJS";
}

if (!sokwanele) { var sokwanele = {}; }

/************************************************************************
 * Object
 *************************************************************************/
sokwanele.polling = function () {
    var self = this;
    var location = '';

    this.windowWidth = function() {
        return window.innerWidth;
    };

    this.isMobile = function() {
        return self.windowWidth() <= 599;
    };

    this.isTablet = function() {
        return self.windowWidth() > 599 && self.windowWidth() <= 1007;
    };

    this.isDesktop = function() {
        return self.windowWidth() > 1007;
    };

    this.addEvent = function(type, listener, element) {
        if (!element) {
            element = window;
        }

        if (element.addEventListener) {
            element.addEventListener(type, listener, false);
        } else if (element.attachEvent) {
            element.attachEvent('on' + type, listener);
        }
    };

    this.init = function () {
        if ('querySelector' in document && 'addEventListener' in window)
        {
            self.generateImages();
            self.addEvent('resize', self.generateImages);
            self.addEvent('change',
                function(e) {
                    window.location.href=document.querySelector('form').action + '/' + this.options[this.selectedIndex].value;
                },
                document.querySelector('#search'));
        }
    };

    this.initMap = function() {
        var map = new google.maps.Map(document.getElementById('mapcontainer'),
            {
                streetViewControl: false,
            }
        );
        var bounds = new google.maps.LatLngBounds();
        for (var i = 0; i < points.length; i++)
        {
            var loc = new google.maps.LatLng(points[i][1],points[i][2]);
            var marker = new google.maps.Marker({
                position: loc,
                map: map,
                title: points[i][0]
            });
            bounds.extend(loc);
        }
        map.fitBounds(bounds);
        map.panToBounds(bounds);
    }

    this.generateImages = function() {
        if (self.getImages().length > 0)
        {
            if (!self.isMobile()) {
                if (self.isTablet()) {
                    self.generateImagesFor('tablet');
                } else {
                    self.initMap();
                    //self.generateImagesFor('desktop');
                }
            }
        }
    };

    this.getImages = function() {
        return document.querySelectorAll('img.resp');
    }

    this.generateImagesFor = function(profile) {

        var imgs = self.getImages();

        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];

            var suffix = (profile === 'tablet') ? 't' : 'd';
            var dataAttribute = 'data-src-' + suffix;
            var className = 'resp-' + suffix;
            if(!img.parentNode.querySelector('img.' + className)) {
                var newImage = document.createElement('img');

                if(img.hasAttribute(dataAttribute)) {
                    newImage.src = img.getAttribute(dataAttribute);
                } else {
                    newImage.src = img.src;
                }

                if(img.hasAttribute('alt')) {
                    newImage.setAttribute('alt', img.getAttribute('alt'));
                }

                if(img.hasAttribute('title')) {
                    newImage.setAttribute('title', img.getAttribute('title'));
                }

                newImage.className = className;

                img.parentNode.insertBefore(newImage, img.nextSibling);
            }
        }
    }

    this.getLocation = function() {
        if(navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                window.location.href = 'polling.php/location/' + position.coords.latitude + '/' + position.coords.longitude;
            }, function() {
                // couldnt get position - manual
            });
        }
    }

    this.init();
}

var polling = new sokwanele.polling();