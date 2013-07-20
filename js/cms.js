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
 * Templates
 *************************************************************************/


/************************************************************************
 * Object
 *************************************************************************/
sokwanele.cms = function () {
    var self = this;
    this.debugErrors = true; // debug

    this.init = function () {
        $(document).ready(function () { self.ready() });
    };

    this.ready = function () {
        self.debug("jquery ready");
        self.initTabs();
        self.populateLookupData();
    };

    this.initTabs = function () {

        $("#Tabs li a").click(function (e) {
            e.preventDefault();
            self.setActiveTab(this);
        });
    };

    this.setActiveTab = function(a)
    {
        self.debug("tab clicked");
        $(a).parent().parent().find("a").removeClass("active");
        $(a).addClass("active");
    }

    this.populateLookupData = function() {
        $.ajax({
            type: 'GET',
            url: 'api.php/parties',
            dataType: "json",
            success: function(e) {
                for (var i = 0; i < e.data.length; i++)
                {
                    $('#party').append($('<option value="' + e.data[i].party_id + '">' + e.data[i].party_name + '</option>'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                alert('api error: ' + textStatus);
            }
        });
    }

    this.candidateFormToJSON = function() {
        return JSON.stringify({
            "party": $('#party').val(),
            "firstname": $('#firstname').val(),
            "surname": $('#surname').val(),
            "constituency": $('#constituency').val(),
            "year": $('#year').val()
        });
    }

    this.debug = function (myMessage) {
        if (this.debugErrors == true) {
            if ((typeof console != "undefined") && (typeof console.log == 'function')) {
                console.log(myMessage);
            }
            else {
                alert(myMessage);
            }
        }
    };

    this.init();
}

var votermap = new sokwanele.cms();