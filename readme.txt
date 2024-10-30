=== JuLiA Beta ===
Contributors: EllieSemantic
Donate link: http://adaptivesemantics.com
Tags: comments, abusiveness, moderation, plugin, admin
Requires at least: 2.7
Tested up to: 2.7
Stable tag: 0.9.1

JuLiA is an automated comment moderation system. Uses natural language processing to parse for the semantics of abusiveness. **ENGLISH ONLY**

== Description ==

JuLiA stands for Just a Linguistic Algorithm. This plugin checks your comments against the JuLiA web service, which supplies an abusiveness score for each comment. Based on this score, comments are auto-published, auto-deleted, or marked for review. Over time, scores are aggregated by commenter in order to generate abusiveness rankings and other statistics.

When the plugin is activated, it will add an "Abusive" category to the top of your comments management screen. When JuLiA parses a comment and decides that it is abusive, it will be removed from your "Pending" category and placed into the "Abusive" category. In addition, when JuLiA decides that a comment is definitely clean then it will simply be auto-published and save you time by skipping the "Pending" category entirely.

JuLiA makes these decisions based not on keywords, but on the overall semantics present in a single comment. For now, JuLiA works on **English comments only.**

= Settings: =

A new top-level "JuLiA" menu will be added to the sidebar of your admin screen, where you can change plugin settings, view your most abusive commenters, and set your list of Flagged and Trusted commenters. Many of these settings are self-explanatory, but the most important are the Auto-Publish and Quarantine thresholds.

Whenever JuLiA parses a comment, she assigns it a score ranging from 100% Abusive to 100% Clean. By default, JuLiA will Auto-Publish a comment if it scores higher than 50% Clean, and will Quarantine a comment if it is more than 80% Abusive. All other comments are sent to the "Pending" category with a recommendation attached. These thresholds can be changed in the "Settings" sub-menu.

= Abusiveness Report: =

Over time, JuLiA scores are aggregated and grouped by commenter to produce a very accurate measure of your most abusive commenters. By default JuLiA will list the most abusive commenters for the past 7 days, but this timeframe can be changed in the Settings menu. You have the option to flag commenters who appear in the abusiveness report, and you can manage flagged and trusted commenters in a different sub-menu.

= Flagged and Trusted Commenters: =

When a commenter is Flagged as abusive, their comments will never be Auto-Published regardless of how they score. When a commenter is marked as Trusted, their comments will *always* be Auto-Published regardless of how they score. By default, your own username is automatically added to the Trusted list. You can add or remove users from this list in the appropriate sub-menus.

== Installation ==

1. Download the zip file and decompress it into the wp-content/plugins/ directory
2. Activate the Plugin through your "Plugins" menu in Wordpress
3. That's it! Incoming comments will show JuLiA recommendations, and you can change settings and view reports in your new top-level "JuLiA" menu.

== Frequently Asked Questions ==

= How does JuLiA work? =

When a new comment hits your blog, it is sent to the JuLiA web service to be parsed. Our proprietary AI turns the comment into a mathematical object and performs some manipulations to determine the amount of abusiveness present. This is represented as a score which is sent back to your blog to be displayed along with the comment.

= What do you mean by "Abusiveness"? =

JuLiA is essentially a machine learning algorithm, which means that she learns to classify objects by example. We have trained the current version with over 10,000 human-tagged comments. In tagging these comments, we used a definition of abusiveness that is in line with the standards of most major online publications.

= Aren't you guys censoring people? =

Actually, no. All that JuLiA does is generate semantic meta-data about a comment that is submitted to our service. Each individual user can then decide what to do with this data by using the settings menu that we provide along with the plugin. In reality, all that we are doing is allowing individual bloggers and publishers to take better control of their user-generated content and maintain their own editorial standards.

= Can't commenters get around this filter by breaking up words, repl@cing lett3rs with symb0ls etc? =

None of the techniques that commenters use to get around traditional keyword filters will work with JuLiA. We are currently using a vocabulary of 840,000 unique features taken from live human comments. Things like l33t speak and bro ken wor ds have already been included in the system as abusive examples, so JuLiA will recognize them.

= Can I customize JuLiA to my own definition of abusiveness? =

One of the unique features of machine learning systems is that they can become more customized and improve over time. Currently we are using a single central algorithm which we re-train in house, but in later releases we will add the ability for users to re-train JuLiA and build their own customized version. Until then you will always be able to exert control over the actions that JuLiA takes, by setting your trust thresholds in the Settings menu.

== Screenshots ==

1. Settings Screen
2. Recommendations
3. Quarantine List
4. Abusiveness Report
