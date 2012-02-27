<?php
class en_us extends Language{
	protected $strings = array(
		"tm" => "&#153;",
		"shit html" => "shit{{tm}}",
		"shit text" => "shit(TM)",
		"shit points html" => "{{shit html}} points",
		"shit points text" => "{{shit text}} points",
		"admin header" => "Administrator Tools",
		"page home" => "Home",
			"page home none" => "You have no {{shit points html}}! Earn more {{shit points html}} at the {{page clicker}}!",
			"page home negative" => "You are {{shit html}} debt. You have less than no {{shit points html}}. You would have more {{shit points html}} if you had never joined {{shit points html}}. I hope you feel good about yourself right now.",
			"page home info title" => "Absorb",
			"page home info text" => array(
							"Have you <strong>stolen</strong> {{shit points html}} from people? It's a fast way to earn {{shit points html}}!",
							"Did you know your {{shit points html}} are stored in the cloud? It's true!",
							"You can earn more {{shit points html}} at the {{page clicker}}!",
							"You can transfer {{shit points html}} between the site and your computer at any time. Click {{page upload}} or {{page download}} to begin."),
		"page about" => "About",
			"page about who" => "{{shit points html}} was created by Tyler Menezes and Paul Cretu.",
		"ashoat title" => "Ashoat Tevosyan (CEO, Awesome Points)",
		"quote" => array(	"&ldquo;It's like the world cup of shit!&rdquo; &mdash;Tyler",
					"&ldquo;I invented the internet simply as a framework for {{shit points html}}&rdquo; &mdash;Sir Tim Berners-Lee",
					"&ldquo;I use {{shit points html}} because they are open source.&rdquo; &mdash; Richard Stallman",
					"&ldquo;I WILL DESTROY YOU ALL&rdquo; &mdash; {{ashoat title}}",
					"&ldquo;Jennifer Schumaker, Paul Cretu, and 159 other people like this.&rdquo; &mdash; Facebook",
					"&ldquo;DIE MUTHAFUCKA DIE&rdquo; &mdash; {{ashoat title}}",
					"&ldquo;{{shit points html}} are the shit.&rdquo; &mdash; Jennifer Schumaker<br />&ldquo;False.&rdquo; &mdash; {{ashoat title}}"),
		"notification stolen" => "Oh no! %s of your {{shit points html}} were stolen by %s!",
		"notification points recieved" => "%s sent you %s {{shit points html}}! :D",
		"notification welcome" => "Welcome to {{shit points html}}! We've started you off with 10 {{shit points html}}! Earn more at the {{page clicker}}!",
		"notification api" => "API Notification",
			"notification api api-awesomepoints" => "Your Awesome Points conversion has been deposited in your account.",
		"permission error" => "You don't have permission.",
	);
	function getLanguageId(){
		return "en_us";
	}
	function getLanguageName(){
		return "United States English";
	}
}
?>
