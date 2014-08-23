var loggedIn;
var addNewShowButton = $("#submit-new-show");
var showNameInput = $("#new-show-name");
var addShowResult = $("#add-show-result");
var showsListP = $("#shows-list");
var airingShowsDiv = $("#airing-shows-div");
var loginButton = $("#login-button");
var registerButton = $("#register-button");
var logOutButton = $("#log-out-button");
var loginForm = $("#login-form");
var registerForm = $("#register-form");
var loginUsernameInput = $("#login-username");
var loginPasswordInput = $("#login-password");
var registerUsernameInput = $("#register-username");
var registerPasswordInput = $("#register-password");
var rememberMeCheckbox = $("#remember-me");
var registerDiv = $("#register-div");
var loginDiv = $("#login-div");
var userControlsDiv = $("#user-controls");
var trackMoreShowsButton = $("#track-more-shows");
var trackMoreShowsDiv = $("#track-more-shows-div");
var trackSelectedShowsButton = $("#track-selected-shows-button");
var markRssAsReadButton = $(".rss-read");

var user = new createUser();
var _MS_PER_DAY = 1000 * 60 * 60 * 24;
var ENTER_KEY_CODE = 13;
var cookieHash;

var TVdata = {};

function createUser() {
	this.name;
	this.id;
	this.combinedHash;
	this.nextEpisodesAiring;
	this.lastNzbID;
	this.rssItems;
	this.trackedShows = [];
	this.untrackedShows = [];
	this.trackedEpisodesInfo = [];
}

function padToTwo(number) {
	if (String(number).length == 1) {
		return '0' + number;
	} else {
		return number;
	}
}

function initRemoveShowButtons() {
	var removeShowButtons = $(".remove-show-table");
	if (!removeShowButtons.length) {
		return;
	}
	removeShowButtons.click(function() {
		var showName = $(this).attr("id");
		$(this).remove();
		var showNameSelector = showName.replace(/ /g, "-");
		showNameSelector = showNameSelector.replace("(", "\\(");
		showNameSelector = showNameSelector.replace(")", "\\)");
		showNameSelector = showNameSelector.replace("&", "\\&");
		showNameSelector = showNameSelector.replace(":", "\\:");
		var listing = $("#" + showNameSelector + "p");
		var listingParentDiv = listing.parent();
		$.ajax({
			data: {"show-name": showName, "user-id": user.id},
			type: "POST",
			url: "remove-show.php",
			success: function(data) {
				if (data.slice(0, 7) == "success") {
					console.log("successfully removed " + showName + " from users tracked shows in db");
					user.trackedShows.splice(user.trackedShows.indexOf(showName), 1);
					
					$.each(user.nextEpisodesAiring, function(index, episode) {
						if (episode.show_name == showName) {
							user.nextEpisodesAiring.splice(user.nextEpisodesAiring.indexOf(episode), 1);
							return false;
						}
					});
					user.untrackedShows.push(showName);
					updateTrackedShows();
					updateUsersUntrackedShows();

					addShowResult.html("successfully removed " + showName);
					if (listingParentDiv.find("p").length == 1) {
						//only one <p> in parent div, so remove div
						listingParentDiv.remove();
					} else {
						//multiple items in parent div, so remove the <p> only
						listing.remove();
					}
				} else {
					console.log("error deleting show from users tracked list in db");
					console.log(data);
				}
			}
		});
	});
}

function updateTrackedShows() {
	var displayShowsList = "";
	if (loggedIn) {
		user.trackedShows.sort();
		$("#tracked-shows > h2").html("Tracked Shows:");
		$.each(user.trackedShows, function(index, title) {
			displayShowsList += title + " <img src='http://goct.ca/tv-calendar/images/delete.png' class='remove-show-table' id='" + title + "'><br/>";
		});
		if ($.isEmptyObject(user.trackedShows)) {
			$("#tracked-shows > h2").html("Tracked Shows:");
			displayShowsList += "Not tracking any shows.";
		}
	} else {
		TVdata.allShowNames.sort();
		$("#tracked-shows").find("h2").html("All Shows:");
		$.each(TVdata.allShowNames, function(index, title) {
			displayShowsList += title + "<br/>";
		});			
	}
	$("#tracked-shows").find("p").html(displayShowsList);
	initRemoveShowButtons();
}

function addShowToDatabase() {
	var showName = showNameInput.val();
	var officialShowName;
	if (!showName || addNewShowButton.css("display") == "none") {
		return;
	}
	addNewShowButton.css({"display": "none"});
	showNameInput.css({"display": "none"});
	showNameInput.val("");
	showName = showName.replace("(", "");
	showName = showName.replace(")", "");
	addShowResult.html("Submitted. Waiting for response from tvrage.com...");
	$.ajax({
		data: {'title': showName},
		type: "GET",
		url: "add-show.php",
		success: function(data) {
			if (data == "duplicate") {
				addShowResult.html("error: show is already being tracked");
			} else {
				officialShowName = data;
				showsList.push(officialShowName);
				updateTrackedShows(showsList);
				addShowResult.html("successfully entered " + officialShowName + " into database");
				airingShowsDiv.empty();
				getStoredEpisodeInfo(null, "all");
			}
		},
		error: function(obj, status, errorMessage) {
			addShowResult.html('error adding new shows: ' + status + ' ' + errorMessage);
		},
		complete: function() {
			addNewShowButton.css({"display": "inline"});
			showNameInput.css({"display": "inline"});
		}
	});	
}

function getAllShowAndEpisodeInfo(callback) {
	$.ajax({
		url: "get-show-and-episode-info.php",
		data: {"shows-to-get": "all"},
		dataType: "json",
		type: "POST",
		success: function(data) {
			TVdata.allShowNames = data["all show names"];
			TVdata.allNextAiringEpisodes = data["next airing episodes"];
			callback(null, "ok");
		},
		error: function(obj, status, errorString) {
			console.log("error retrieving full episode info");
			console.log(status + ": " + errorString);
			callback("error", "not ok");
		}
	});
}

function getBBDay(dateObj) {
	var moveInDate = new Date("2014-06-20"); //day the bb houseguests moved into the house
	var BBDay = dateDiffInDays(dateObj, moveInDate);
	return BBDay * -1;
}

function dateDiffInDays(a, b) {
  // Discard the time and time-zone information.
  var utc1 = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
  var utc2 = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());

  return Math.floor((utc2 - utc1) / _MS_PER_DAY);
}

function displayEpisodeInformation(nextEpisodesAiring, recentlyAiredEpisodes) {
	//console.log(nextEpisodesAiring);
	var divTitleText;
	var daysAiringIn = {};
	
	if ($.isEmptyObject(nextEpisodesAiring)) {
		airingShowsDiv.append
		(
		"<div class='future-episodes-day'>"
		+ "<h3>You have no tracked shows with upcoming air dates.</h3>"
		+ "</div>"
		);
		return;
	}
	
	$.each(nextEpisodesAiring, function(index, episode) {
		//custom format Big Brother US episodes by concatenating the BBday
		if (episode["show_name"] == "Big Brother (US)") {// && episode["title"].toLowerCase().indexOf("live") !== -1) {	
			if (episode["episode_title"].indexOf("(BB Day ") == -1) {
				var airDateObj = new Date(episode["air_date"]);
				episode["episode_title"] = episode["episode_title"] + " (BB Day " + getBBDay(airDateObj) + ")";
			}
		}
		if (!daysAiringIn[episode["days_airing_in"]]) {
			daysAiringIn[episode["days_airing_in"]] = [];
		}
		daysAiringIn[episode["days_airing_in"]].push(episode);
	});
	$.each(daysAiringIn, function(days, episodes) {
		if (days == 0) {
			divTitleText = "<h3>Airing Today</h3>";
		} else if (days == 1) {
			divTitleText = "<h3>Airing Tomorrow</h3>";
		} else {
			divTitleText = "<h3>Airing in " + days + " days</h3>";
		}
		airingShowsDiv.append("<div class='future-episodes-day'>" + divTitleText + "</div>");
		$.each(episodes, function(index, episode) {
			var showName = episode["show_name"];
			var seasonNum = padToTwo(episode["season_num"]);
			var episodeNum = padToTwo(episode["episode_num"]);
			var episodeTitle = episode["episode_title"];
			var lastUpdated = episode["last_updated"];
			airingShowsDiv.find("div:last").append("<p id='" + showName.replace(/ /g, "-") + "p'>" + showName + " - S" + seasonNum + "E" + episodeNum + " - " + episodeTitle + "</p>");		
		});
	});
	$("#tracked-shows").css("display", "block");
}

function updateScrapeTimer(lastScrapeTS) {
	var nowTS = Date.now() / 1000;
	var TSDiff = nowTS - lastScrapeTS;
	var msec = (1200 - TSDiff) * 1000;
	var hh = Math.floor(msec / 1000 / 60 / 60);
	msec -= hh * 1000 * 60 * 60;
	var mm = Math.floor(msec / 1000 / 60);
	msec -= mm * 1000 * 60;
	var ss = Math.floor(msec / 1000);
	//msec -= ss * 1000;
	if (mm > 0) {
		if (mm > 20) {
			$("#next-scrape-timer").text("A bug occurred. Try reloading the page in a few seconds"); //fix this
			clearInterval(timerUpdater);
			return;
		}
		var nextScrapeString = "Next update: " + mm + " min, " + ss + " sec";
		$("#next-scrape-timer").text(nextScrapeString);
	} else if (ss > 0) {
		var nextScrapeString = "Next update: " + ss + " sec";
		$("#next-scrape-timer").text(nextScrapeString);
	} else {
		$("#next-scrape-timer").text("Next update is available upon page refresh.");
		clearInterval(timerUpdater);
		//if (!$("#latest-nzbs-div").length) {
			$("#next-scrape-timer").text("Reloading page within 10 seconds...");
			setTimeout(function() {
				location.reload();
			}, 10000);
		//}
	}
}

function displayRssItems(rssItems, lastScrapeTS) {
	var trackedShowsWithUpdates = [];
	$("#rss").append("<p id='next-scrape-timer'></p>");
	updateScrapeTimer(lastScrapeTS);
	timerUpdater = setInterval(function() {
		updateScrapeTimer(lastScrapeTS);
	}, 1000);

	if (!rssItems.length) {
		$(".rss-read").remove();
		$("#latest-nzbs-div").remove();
		$("#next-scrape-timer").css("display", "inline");
		return;
	}
	for (var i = 0; i < rssItems.length; i++) {
		if (user.trackedShows.indexOf(rssItems[i]) !== -1) {
			//user is tracking this show
			trackedShowsWithUpdates.push(rssItems[i]);
			rssItems.splice(i, 1);
			i--;
		}
	};
	rssItems = trackedShowsWithUpdates.concat(rssItems);
	for (var i = 0; i < rssItems.length; i++) {
		var item = rssItems[i];
		var showName = item["show name"];
		var rawTitle = item["raw title"];
		var seriesID = item["series id"];
		var epNum = padToTwo(item["episode"]);
		var seasonNum = padToTwo(item["season"]);
		var epName = item["episode name"];
		var itemID = item["item id"];
		var downloadLink = item["download_link"];
		var displayLink = "<li><a href='" + downloadLink + "' class='item-link'>" + rawTitle + "</a></li>";

		if (!seriesID) {
			if (!$("#seriesID0").length) {
				//div doesn't exist for blank series id, so create it
				$("#latest-nzbs-div").append(
					"<h3>Uncategorized</h3>" +
					"<div id='seriesID0'>" +
						"<ul id='seriesID0-list' class='series-list'></ul>" +
					"</div>"
				);
			}
			$("#seriesID0-list").append(displayLink);
		} else {
			if (!$("#seriesID" + seriesID).length) {
				//div doesn't exist for this series id, so create it
				var className = "untracked";
				if (user.trackedShows.indexOf(showName) !== -1) {
					className = "tracked";
				}
				$("#latest-nzbs-div").append(
					"<h3 class='" + className + "'>" + showName + "</h3>" +
					"<div id='seriesID" + seriesID + "'>" +
						"<ul id='seriesID" + seriesID + "-list' class='series-list'></ul>" +
					"</div>"
				);
			}
			$("#seriesID" + seriesID + "-list").append(displayLink);
		}
	};
	$("#latest-nzbs-div").accordion({
	  collapsible: true,
	  active: false,
	  heightStyle: "content"
	});
	$(".rss-read").css("display", "block");
	$("#next-scrape-timer").css("display", "none");
}

markRssAsReadButton.click(function() {
	$("#latest-nzbs-div").remove();
	$(".rss-read").remove();
	$("#next-scrape-timer").css("display", "inline");
	$.ajax({
		url: "update-last-nzb-viewed.php",
		data: {"user_id": user.id, "last_nzb_id": user.lastNzbID},
		dataType: "text",
		type: "POST",
		success: function(data) {
			console.log("successfully updated last nzb id viewed");
		},
		error: function(obj, status, errorString) {
			console.log("error while updating last nzb id viewed");
		}
	});
});

addNewShowButton.click(function() {
	addShowToDatabase();
});

loginForm.submit(function() {
	var username = loginUsernameInput.val();
	var pw = loginPasswordInput.val();
	site.login(username, pw, null);
	return false;
});


function updateUsersUntrackedShows() {
	var untrackedShowsDisplayString = "";
	
	//for (var i = 0; i <= TVdata.allShowNames.length; i++) {
	$.each(TVdata.allShowNames, function(index, showName) {
		if (user.trackedShows.indexOf(showName) == -1) {
			//user is not tracking show
			if (user.untrackedShows.indexOf(showName) == -1) {
				//it isn't already on untracked shows list
				user.untrackedShows.push(showName);
			}
		} else if (user.untrackedShows.indexOf(showName) != -1) {
			//they are tracking the show, so remove it from untracked shows list
			user.untrackedShows.splice(user.untrackedShows.indexOf(showName), 1);
		}
	});
	
	user.untrackedShows.sort();
	
	if (user.trackedShows.length != TVdata.allShowNames.length) {
		//user isn't tracking every show
		trackSelectedShowsButton.css("display", "block");
		$.each(user.untrackedShows, function(index, show) {
			untrackedShowsDisplayString += show
			+ "<input type='checkbox' id='track-" + show + "' class='track-show-checkbox'><br/>";
		});
	} else {
		untrackedShowsDisplayString += "You're currently tracking every show in the database.";
		trackSelectedShowsButton.css("display", "none");
	}
	trackMoreShowsDiv.find("#shows-list").html(untrackedShowsDisplayString);
}

registerForm.submit(function() {
	var username = registerUsernameInput.val();
	var pw = registerPasswordInput.val();
	if (!username || !pw) {
		alert("Please fill out both fields");
		return false;
	}
	
	$.ajax({
		url: "register.php",
		type: "POST",
		data: {"username": username, "pw": pw},
		dataType: "json",
		success: function(data) {
			var status = data["status"];
			switch(status) {
				case "success":
					alert("Successfully added user. You may now log in.");
					registerDiv.css("display", "none");
					break;
				case "duplicate user":
					alert("That username is already taken. Please choose a different one.");
					break;
				case "error":
					var errorMsg = data["errormsg"];
					console.log("error: " + errorMsg);
					break;
			}
		},
		error: function(obj, status, errorString) {
			console.log("--Error while submitting registration form--");
			console.log(status + ": " + errorString);
		}
	});
	
	return false;
});

logOutButton.click(function() {
	site.logout();
});

trackMoreShowsButton.click(function() {
	if (trackMoreShowsDiv.css("display") == "block") {
		trackMoreShowsDiv.css("display", "none");
	} else {
		trackMoreShowsDiv.css("display", "block");
	}
});

trackSelectedShowsButton.click(function() {
	var newlyTrackedShows = [];
	var username = user.name;
	if (!$(".track-show-checkbox:checked").length) {
		//user didn't check any new shows
		trackMoreShowsDiv.css("display", "none");
		return;
	}
	$.each($(".track-show-checkbox:checked"), function(index, checkbox) {
		var showName = $(checkbox).attr("id").slice(6);
		user.trackedShows.push(showName);
		user.untrackedShows.splice(user.untrackedShows.indexOf(showName), 1);
		newlyTrackedShows.push(showName);
	});
	trackMoreShowsDiv.find("p").html("");
	updateTrackedShows();
	updateUsersUntrackedShows();
	trackMoreShowsDiv.css("display", "none");
	
	//we now need to update the users next episodes airing object
	$.each(newlyTrackedShows, function(index, newlyTrackedShowName) {
		$.each(TVdata.allNextAiringEpisodes, function(index, episode) {
			if (episode["show_name"] == newlyTrackedShowName) {
				user.nextEpisodesAiring.push(episode);
				return false;
			}
		});
	});
	
	airingShowsDiv.empty();
	displayEpisodeInformation(user.nextEpisodesAiring, null);
	
	$.ajax({
		url: "update-users-shows.php",
		type: "POST",
		data: {"newly-tracked-shows": JSON.stringify(newlyTrackedShows), "user-id": user.id},
		datatype: "text",
		success: function(data) {
			console.log("successfully updated users tracked shows in the db");
		},
		error: function() {
			console.log("error function from update users shows ajax call");
		}
	});
});

$("body").keydown(function(event) {
	if (event.which == ENTER_KEY_CODE) {
		if ($(":focus").is(showNameInput)) {
			addShowToDatabase();
		}
	}
});


var site = new makeSiteInstance();
repositionElements();

if (cookieHash = $.cookie("hash")) {
	//attempting to autologin
	site.login(null, null, cookieHash);
} else {
	//send the user to the main page
	site.showMainPage();
}

window.onresize = repositionElements;


function repositionElements() {
	var mainContentDivWidth = parseInt($("#main-content").css("width"));
	var futureEpisodesDayWidth = 530;//parseInt($(".future-episodes-day :first").css("width"));
	var goodAccordionWidth = (mainContentDivWidth - futureEpisodesDayWidth) / 2 - 50;
	$("#rss").css("width", goodAccordionWidth);
	//$("#latest-nzbs-div").css("width", goodAccordionWidth);
}

function makeSiteInstance() {
	this.showMainPage = function() {
		loginDiv.css("display", "block");
		registerDiv.css("display", "block");
		async.series([
			function(callback) {
				if (!TVdata.allNextAiringEpisodes) {
					//we need to get show names and episode info from db
					getAllShowAndEpisodeInfo(callback);
				} else {
					callback(null, "proceed");
				}
			},
			function(callback) {
				//display show names and episode info
				displayEpisodeInformation(TVdata.allNextAiringEpisodes, null);
				updateTrackedShows();
				callback(null, "proceed");
			}
		],
		// optional callback
		function(err, results){
			//console.log("err is " + err);
			//console.log("results is " + results);
		});
	}
	
	this.login = function(username, pw, hash) {
		if ((!username || !pw) && !hash) {
			alert("Please fill out both fields.");
			return;
		}

		var rememberMe;
		if ($("#remember-me:checked").length) {
			rememberMe = true;
		} else {
			rememberMe = false;
		}
		
		async.series([
			function(callback) {
				//authenticate user
				$.ajax({
					url: "login.php",
					type: "POST",
					data: {"username": username, "pw": pw, "remember-me": rememberMe, "autologin-hash": hash},
					dataType: "json",
					success: function(data) {
						//user is now logged in
						loggedIn = true;
						user.nextEpisodesAiring = data["next airing episodes"];
						user.combinedHash = data["combined hash"];
						user.name = data["username"];
						user.trackedShows = data["users tracked show names"];
						user.id = data["user id"];
						user.rssItems = data["rss items"];
						user.lastNzbID = data["last nzb id"];
						user.lastScrapeTS = data["last scrape ts"];
						
						if (data["login type"] == "manual" && $("#remember-me").is(':checked')) {
							//set login cookie
							$.ajax({
								url: "set-login-cookie.php",
								type: "POST",
								data: {"combined hash": user.combinedHash},
								dataType: "text",
								success: function(data) {
									console.log("successfully set login cookie");
								},
								error: function() {
									console.log("--error after calling the set-login-cookie script");
								},
							});
						}
						
						callback(null, 'logged in');

					},
					error: function(obj, status, errorString) {
						console.log(obj);
						console.log("errorString is " + errorString);
						callback("bad credentials");
					}
				});
			},
			function(callback) {
				//display users tracked show information
				updateTrackedShows();
				
				//empty the airing shows div and repopulate it with users specific episodes
				airingShowsDiv.empty();
				displayEpisodeInformation(user.nextEpisodesAiring, null);
				displayRssItems(user.rssItems, user.lastScrapeTS);
				$("#user-info-divs").css("display", "none");
				$("#logged-in-username").html(user.name);
				userControlsDiv.css("display", "block");
				$(".rss-read").css("display", "block");
				callback(null, "proceed");
			},
			function(callback) {
				getAllShowAndEpisodeInfo(callback);
			},
			function(callback) {
				updateUsersUntrackedShows();
				callback(null, "proceed");
			}
		],
		// optional callback
		function(err, results){
			console.log("err is " + err);
			console.log("results is " + results);
		});
	}
	
	this.logout = function() {
		loggedIn = false;
		$.removeCookie("hash");
		$(".rss-read").css("display", "none");
		userControlsDiv.css("display", "none");
		trackMoreShowsDiv.css("display", "none");
		airingShowsDiv.empty();
		$("#latest-nzbs-div").accordion("destroy");
		$("#latest-nzbs-div").empty();
		
		async.series([
			function(callback) {
				if (!TVdata.allShowNames) {
					getAllShowAndEpisodeInfo(callback);
				} else {
					//we already have the data for the main page
					callback(null, "proceed");
				}
			},
			function(callback) {
				loginDiv.css("display", "block");
				registerDiv.css("display", "block");
				$("#user-info-divs").css("display", "block");
				displayEpisodeInformation(TVdata.allNextAiringEpisodes, null);
				updateTrackedShows();
				callback(null, "proceed");
			}
		],
		// optional callback
		function(err, results){
			console.log("err is " + err);
			console.log("results is " + results);
		});
	}
	
}


/* testing area!!! */
/*
var site = new makeSiteInstance();
cookieHash = $.cookie("hash");

site.login(null, null, cookieHash);

*/

/*------------------*/

