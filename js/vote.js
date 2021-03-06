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

var tableTemplate = "<table class='resultstable'><thead><tr><th colspan='2' class='name'>Constituency</th><th>% won</th><th>% turnout</th></tr></thead>" +
    "<tbody>{{#items}}<tr><td class='partytag' style='background:{{colour}}'></td><td class='name'><a href='#' turnout='{{turnout}}' cid='{{id}}'>{{name}}</a></td><td class='val'>{{won}}</td><td class='val'>{{turnout}}</td></tr>{{/items}}</tbody></table>";

var marginTableTemplate = "<table class='resultstable'><thead><tr><th colspan='2' class='name'>Constituency</th><th>% margin</th><th>% turnout</th></tr></thead>" +
    "<tbody>{{#items}}<tr><td class='partytag' style='background:{{colour}}'></td><td class='name'><a href='#' turnout='{{turnout}}' cid='{{id}}'>{{name}}</a></td><td class='val'>{{won}}</td><td class='val'>{{turnout}}</td></tr>{{/items}}</tbody></table>";

var PRTableTemplate = "{{#provinces}}<h3>{{name}}</h3><table class='resultstable'><thead><tr><th colspan='2' class='name'>Party</th><th>seats</th><th>% turnout</th></tr></thead>" +
    "<tbody>{{#items}}<tr><td class='partytag' style='background:{{colour}}'></td><td class='name'><a href='#' turnout='{{turnout}}' cid='{{id}}'>{{name}}</a></td><td class='val'>{{votes}}</td><td class='val'>{{turnout}}</td></tr>{{/items}}</tbody></table>{{/provinces}}";

var tooltipTemplate = "<div class='tooltipview'><h3>{{name}}</h3><table class='resultstable'><thead><tr>" +
    "<th colspan='2' class='name'>Party</th><th>{{voteheading}}</th></tr></thead><tbody>{{#items}}<tr>" +
    "<td class='partytag' style='background:{{colour}}'></td><td class='name'>{{name}}</td>" +
    "<td class='val'>{{votes}}</td></tr>{{/items}}</tbody></table></div>";

var tooltipBG = "<div class='tooltipview'><h3>{{name}}</h3><h4>{{margin}}% margin</h4><table class='resultstable'><thead><tr>" +
    "<th colspan='2' class='name'>Party</th><th>percent</th></tr></thead><tbody>{{#items}}<tr>" +
    "<td class='partytag' style='background:{{colour}}'></td><td class='name'>{{name}}</td>" +
    "<td class='val'>{{votes}}</td></tr>{{/items}}</tbody></table></div>";

var detailTemplate = "<div id='detailchart'></div><img id='detailswing'/><h3><a href='http://www.sokwanele.com/zimbabwe-elections/constituency/{{name}}'>{{name}}</a></h3><h4>Turnout: {{turnout}}%</h4>" +
    "<table class='detailtable'><tbody>{{#items}}"+
    "<tr><td class='partytag' style='background:{{colour}}'></td><td>{{name}}</td><td>{{party}}</td>"+
    "<td>{{votes}}</td><td>{{percent}}</td></tr>{{/items}}</tbody></table>";

/************************************************************************
 * Object
 *************************************************************************/
sokwanele.vote = function () {
    var self = this;
    this.map = null;
    this.debugErrors = false; // debug
    this.activeRace = 'house';
    this.activeYear = '2013';
    this.polygons = new Array();
    this.defaultColor = '#999999';
    this.constituencies = new Array();
    this.overPolygon = false;

    this.init = function () {
        self.debug("init");
        google.load("visualization", "1", {packages:["corechart"]});
        google.maps.event.addDomListener(window, 'load', self.initMap);
        google.setOnLoadCallback(self.drawChart);

        $(document).ready(function () { self.ready() });
    };

    this.ready = function () {
        self.debug("jquery ready");
        self.initTabs();
        $("#helpbutton").click(function (e) { $('#helpoverlay').show();});
        $("#closebutton").click(function (e) { $('#helpoverlay').hide();});
    };

    this.commaSeparateNumber = function(val){
        while (/(\d+)(\d{3})/.test(val.toString())){
            val = val.toString().replace(/(\d+)(\d{3})/, '$1'+','+'$2');
        }
        return val;
    }

    this.initTabs = function () {

        $("#Tabs li a").click(function (e) {
            e.preventDefault();
            self.setActiveTab(this);
            self.activeRace = $(this).attr("href").replace('#','');
            self.addConstituencies();
            self.drawChart();
            $('#detail').html('');
            $('#marginkey').css('display', (self.activeRace=='battleground' ? 'block' : 'none') );

        });

        $("#StagePicker li a").click(function (e) {
            e.preventDefault();
            self.setActiveTab(this);
            $('#map').animate({left: $(this).attr('href') == '#map' ? '0px' : '-847px'});
            $('#table').animate({left: $(this).attr('href') == '#map' ? '847px' : '0px'});
            $('#stage').css('overflow-y', $(this).attr('href') == '#map' ? 'hidden' : 'auto');
        });
    };

    this.drawChart = function() {

        $.ajax({
            type: 'GET',
            url: 'api.php/results/party/' + self.activeRace + '/' + self.activeYear,
            dataType: "json",
            success: function(e) {
                if (e.data)
                {
                    var headingArray = ['Year'];
                    var voteColors = [];
                    var valueArray = [self.activeRace == 'president' ? 'Votes' : 'Seats'];
                    $.each(e.data, function(i, item) {
                        if (item.percent == null) {item.percent = 0;}
                        var n = item.name;
                        if ((self.activeRace == 'president'))
                        {
                            n = item.name + ' (' + item.percent + '%)';
                        }
                        if ((self.activeRace == 'house' && self.activeYear == '2013') || self.isPR())
                        {
                            n = item.name + ' (' + item.votes + ')';
                        }

                        headingArray.push(n);
                        valueArray.push(parseInt(item.votes));
                        if (item.colour){
                            voteColors.push(item.colour);
                        }
                        else
                        {
                            voteColors.push('#333');
                        }
                    });

                    var data = google.visualization.arrayToDataTable([headingArray, valueArray]);
                    var options = { animation: {duration:100}, width:976, height:100, colors: voteColors, legend: {position:'top'}};
                    var chart = new google.visualization.BarChart(document.getElementById('ResultsChart'));
                    chart.draw(data, options);
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                self.debug('api error: ' + textStatus);
            }
        });

    }

    this.setActiveTab = function(a)
    {
        self.debug("tab clicked");
        $(a).parent().parent().find("a").removeClass("active");
        $(a).addClass("active");
    }

    this.initMap = function () {

        self.debug("google maps ready");

        var styles = [];

        var styledMap2008 = new google.maps.StyledMapType(styles, {name: "2008"});
        var styledMap2013 = new google.maps.StyledMapType(styles, {name: "2013"});

        self.mapCenter = new google.maps.LatLng(-18.895893, 28.894043);
        self.map = new google.maps.Map(document.getElementById('map-canvas'),
            {
                zoom: 7,
                center: self.mapCenter,
                minZoom: 7,
                maxZoom: 11,
                streetViewControl: false,
                mapTypeControlOptions: {
                    mapTypeIds: ['2008', '2013']
                },
                mapTypeId: self.activeYear
            });
        self.map.mapTypes.set('2008', styledMap2008);
        self.map.mapTypes.set('2013', styledMap2013);

        google.maps.event.addListener(self.map,"maptypeid_changed",function(){
            self.activeYear = self.map.getMapTypeId();
            $('h1').html('Zimbabwe Election ' + self.activeYear);
            $('#tabhouselist').css('display', (self.activeYear=='2013' ? 'block' : 'none') );
            $('#tabbattleground a').text((self.activeYear=='2013' ? 'margin' : 'battleground') );
            self.addConstituencies();
            self.drawChart();
        });

        $('#tooltip').poshytip({
            followCursor: true,
            slide: false,
            className: 'tip-twitter',
            showTimeout: 1,
            alignTo: 'cursor',
            keepInViewport: true
        });

        $('#map-canvas').mousemove(function(e){
            $('#tooltip').poshytip("mousemove", e);
        });

        self.addConstituencies();
    };

    this.addConstituencies = function() {

        $('#loading').show();

        self.constituencies = new Array();

        // clear current polygons
        for (var i = 0; i < self.polygons.length; i++)
        {
            self.polygons[i].setMap(null);
        }
        self.polygons = new Array();

        $.ajax({
            type: 'GET',
            url: 'api.php/constituencies/' + self.activeRace + '/' + self.activeYear,
            dataType: "json",
            success: function(e) {
                self.populateTable(e);
                for (var i = 0; i < e.data.length; i++)
                {
                    self.addPolygon(e.data[i]);
                }
                $('#loading').hide();

            },
            error: function(jqXHR, textStatus, errorThrown){
                self.debug('api error: ' + textStatus);
            }
        });
    };

    this.populateTable = function(e) {

        var colSize = e.data.length/3;

        if (self.isPR())
        {
            var provinces = [];

            $.ajax({
                type: 'GET',
                url: 'api.php/results/pr/' + self.activeRace + '/' + self.activeYear,
                dataType: "json",
                success: function(e) {
                    var province = '';
                    var provinceresults = [];

                    for (var i = 0; i < e.data.length; i++)
                    {
                        var row = e.data[i];
                        if (row.province != province)
                        {
                            province = row.province;
                            provinceresults = [];
                            provinces.push({name: province, items: provinceresults});
                        }
                        provinceresults.push(row);
                    }

                    var col1Data = {provinces: provinces.slice(0, colSize) };
                    var col2Data = {provinces: provinces.slice(colSize, colSize * 2)};
                    var col3Data = {provinces: provinces.slice(colSize * 2, colSize * 3) };

                    $('#tablecolumn1').html(Mustache.render(PRTableTemplate, col1Data));
                    $('#tablecolumn2').html(Mustache.render(PRTableTemplate, col2Data));
                    $('#tablecolumn3').html(Mustache.render(PRTableTemplate, col3Data));
                }
            });
        }
        else
        {
            var col1Data = {items: e.data.slice(0, colSize) };
            var col2Data = {items: e.data.slice(colSize, colSize * 2)};
            var col3Data = {items: e.data.slice(colSize * 2, colSize * 3) };

            var template = (self.activeRace=='battleground') ? marginTableTemplate : tableTemplate;

            $('#tablecolumn1').html(Mustache.render(template, col1Data));
            $('#tablecolumn2').html(Mustache.render(template, col2Data));
            $('#tablecolumn3').html(Mustache.render(template, col3Data));

            $('.resultstable .name a').click(function(e){
                self.getConstituencyResults($(this).attr('cid'), $(this).html(), $(this).attr('turnout'));
                e.preventDefault();
            });
        }
    }

    this.addPolygon = function(c) {

        var xmlDoc = $.parseXML(c.geometry);
        var returnGeom = $(xmlDoc).find('coordinates');
        var geomAry = returnGeom.text().split(',0.0 ');
        var XY = new Array();
        var points = [];
        for (var j = 0; j < geomAry.length; j++)
        {
            XY = geomAry[j].split(',');
            points.push( new google.maps.LatLng(parseFloat(XY[1]),parseFloat(XY[0]))) ;
        }
        var polyColor = c.colour != '' && c.colour != null ? c.colour : self.defaultColor;
        var polygon = new google.maps.Polygon({
            'paths':points,
            'strokeColor': "#fff",
            'strokeOpacity': 1,
            'strokeWeight': 1,
            'fillColor': polyColor,
            'fillOpacity': 1,
            'map' : self.map
        });

        google.maps.event.addListener(polygon,"click",function(e){
            //this.setOptions({fillOpacity: "0.8"});
            self.getConstituencyResults(c.id, c.name, c.turnout);
        });

        google.maps.event.addListener(polygon,"mouseover",function(e){
            //this.setOptions({fillOpacity: "0.8"});
            $('#tooltip').poshytip("update",  "Loading...");
            self.overPolygon = true;
            self.getConstituencyResultsSummary(e, c.name, c.id, c.margin);
        });

        google.maps.event.addListener(polygon,"mouseout",function(){
            self.overPolygon = false;
            $('#tooltip').poshytip("hide");
        });

        self.polygons.push(polygon);
    }

    this.getConstituencyResults = function(id, name, turnout, voters)
    {
        $.ajax({
            type: 'GET',
            url: 'api.php/results/' + self.activeRace + '/' + self.activeYear + '/' + id,
            dataType: "json",
            success: function(e) {
                var data = {name: name, turnout: turnout, voters: voters, items: e.data }
                $('#detail').html(Mustache.render(detailTemplate, data));

                var pieDataArray = [['Candidate','Votes']];
                var pieColors = [];
                $.each(e.data, function(i, item) {
                    pieDataArray.push([item.name, parseInt(item.votes)]);
                    pieColors.push(item.colour);
                });

                var options = { width:200, colors: pieColors, legend: {position:'none'}};

                var pieData = google.visualization.arrayToDataTable(pieDataArray);
                new google.visualization.PieChart(document.getElementById('detailchart')).
                    draw(pieData, options);

                if (self.activeYear == '2013' && (self.activeRace == 'president' || self.activeRace == 'house'))
                {
                    $.ajax({
                            type: 'GET',
                            url: 'api.php/swing/' + self.activeRace + '/' + id,
                            dataType: "json",
                            success: function(e) {

                                if (e.data.swing != null)
                                {
                                    var fromColor = e.data.fromcolor.replace('#','');
                                    var toColour = e.data.tocolour.replace('#','');
                                    var swing = 50 + parseFloat(e.data.swing);
                                    $('#detailswing').attr('src',
                                        'https://chart.googleapis.com/chart?chs=200x150&cht=gom&chd=t:' + swing +
                                        '&chco=' + fromColor + ',' + toColour + '&chl=' + e.data.swing + '% swing');
                                }
                            }
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                self.debug('api error: ' + textStatus);
            }
        });
    }


    this.getConstituencyResultsSummary = function(evt, name, id, margin)
    {
        if (id in self.constituencies)
        {
            if (self.overPolygon)
            {
                $('#tooltip').poshytip("update",  self.constituencies[id]);
                $('#tooltip').poshytip("mouseenter", evt);
            }
        }
        else
        {
            $.ajax({
                type: 'GET',
                url: 'api.php/resultssummary/' + self.activeRace + '/' + self.activeYear + '/' + id,
                dataType: "json",
                success: function(e) {
                    var voteheading = (self.isPR()) ? 'seats' : 'votes';
                    var data = {name: name, margin: margin, voteheading: voteheading, items: e.data }
                    var template  = (margin != null) ? tooltipBG : tooltipTemplate;

                    self.constituencies[id] = Mustache.render(template, data);
                    if (self.overPolygon)
                    {
                        $('#tooltip').poshytip("update",  self.constituencies[id]);
                        $('#tooltip').poshytip("mouseenter", evt);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){
                    self.debug('api error: ' + textStatus);
                }
            });
        }
    }

    this.isPR = function()
    {
        return self.activeRace == 'houselist' || (self.activeRace == 'senate' && self.activeYear == '2013');
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

var votermap = new sokwanele.vote();