function setPercentage(percentage) {
	totWidth = 300;
	newWidth = totWidth - (percentage * totWidth/100) + 'px';
	document.getElementById('overlay').style.width = newWidth;
}

function startLoad() {	
	setPercentage(0);
	ID = setInterval('incPercentage(1, ID)', 100);
}

function incPercentage(amount, ID) {
	var oldPercent = (300 - document.getElementById('overlay').style.width.replace(/px,*\)*/g,""))/3;
	if (oldPercent < 100) {
		setPercentage(oldPercent + amount);
	}
	else {
		clearInterval(ID);
		alertFinish();
	}
}
function alertFinish() {
	document.getElementById('doneText').innerHTML = 'Download Complete';

}

function fillCenterDiv() {
	document.getElementById('centerDiv').innerHTML = '<div style="margin-top: 11em;"><div id="loadBar" class="loadBar hcenter vpos"><img class="overlay" id="overlay" src="images/white.gif" /></div><div id="doneText" class="hctext smtxt topmargin1">Downloading Shit&#153 points</div></div>';
}

function startDownload() {
	fillCenterDiv();
	startLoad();
}

function setCenterDiv() {
	document.getElementById('centerDiv').innerHTML = '<div onClick="startDownload()" style="margin-top: 12em;"><div class="hctext smtxt">All Shit&trade; Points will be downloaded to your local machine. (They can be re-uploaded if you wish later.)<br />Click to start Download.</div></div>';
}

function bodyLoad() {
	setCenterDiv();
	startDownload();
}
