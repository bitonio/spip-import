<?php

/*
Plugin Name: spip_import
Plugin URI: http://blog.tcrouzet.com/tag/wp2epub/
Description: Import Spip blog (posts and comments)
Author: Thierry Crouzet
Version: 1.0
Author URI: http://blog.tcrouzet.com/
*/

//Your serveur infos here
define('SPIP_HOST', 'localhost');
define('SPIP_USER', 'yourmysqlusername');
define('SPIP_PSW',  'yourmysqlpsw');
define('SPIP_BASE', 'yourbase');

class spip{

	//OLD spip categories => WP new catégories You have to edit
	var $cat=array('38' => '3','31' => '9','10' => '11','3' => '12','26' => '13','20' => '14','1' => '15','4' => '16','25' => '17','29' => '10','34' => '18','36' => '19','35' => '20','37' => '21','2' => '4','39' => '5','40' => '6','42' => '7','52' => '8','21' => '14','24' => '14');
	
	var $spip_myid = false;
	
	function __construct(){
		set_time_limit(0);
	}

	//Adds a settings link on the plugins page
	function addConfigureLink($links, $file) {
		static $this_plugin;
		if (!$this_plugin) {
			$this_plugin = plugin_basename(__FILE__);
		}
		if ($file == $this_plugin){
			$settings_link = '<a href="tools.php?page=spip_import.php">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link); // before other links
		}
		return $links;
	}
	
	function menu() {
		global $wpdb;
		echo "<div class='wrap'>";
		echo '<h2>Spip import</h2>';
		echo '<p>Import a Spip blog.</p>';
		
		if(!$this->connect()){
			echo ("<p>Problème de connection ".mysql_error())."</p>";
			echo ("<p>Edit the plugin to insert your spip database parameter.</p>");
			echo '</div>';
			return;
		}
		
		if(empty($_POST['do_ok'])){
			echo '<ol style="list-style-type:decimal">';
			echo '<li>Desactive all plugins</li>';
			echo '<li>Your spip dabase must differt from de WP one.</li>';
			echo '<li>Configure your permalinks to match the old spip one.</li>';
			echo '<li>If permalinks impossible to match, modify htaccess with the code generated at the end of the process.</li>';
			echo '</ol>';
			echo '<form method="post">';
			echo '<input type="submit" name="do_ok" value="Import" / ></p>';
			echo '</form>';
			//$this->test();
		}else{

			//Import
			
			$slugs=array();	//Mem old url
			
			$sql="SELECT * FROM spip_articles,spip_urls WHERE type='article' AND id_article=id_objet";
			//$sql.=" AND id_article=1028";
			$result=mysql_query($sql,$this->spip_myid);
			if(!$result){
				echo mysql_error().'</div>';
				return;
			}
			while($row=mysql_fetch_object($result)){
				$slugs[]=$row->url;
				
				if(isset($this->cat[$row->id_rubrique])) $ncat=array($this->cat[$row->id_rubrique]); else $ncat=array();
				if(empty($ncat)) $ncat=array();
				
				//echo(htmlentities($row->texte));
				$data = array(
					'post_content' => $this->post_format(trim($row->chapo. "".$row->texte))
					, 'post_title' => $row->titre
					, 'post_excerpt' => $row->descriptif
					, 'post_name' => $row->url
					, 'post_date' => $row->date
					, 'post_date_gmt' => $row->date
					, 'post_category' => $ncat
					, 'post_status' => 'publish'
					, 'post_author' => '1'
				);
				//http://codex.wordpress.org/Function_Reference/wp_insert_post
				$ID=wp_insert_post($data);
				echo '<p>Insert: '.$row->titre." ".$ID."</p>";
				//echo($this->post_format($row->texte));
				
				//Comments
				if($ID>0){
					$c=0;
					$sql="SELECT * FROM spip_forum WHERE id_article=$row->id_article AND 	statut='publie'";
					$resultcom=mysql_query($sql,$this->spip_myid);
					while($com=mysql_fetch_object($resultcom)){
						if(empty($com->auteur)) continue;
						$comtexte=$this->post_format($com->texte);
						if(empty($comtexte)) continue;
						$data = array(
							'comment_post_ID' => $ID
							, 'comment_author' => $com->auteur
							, 'comment_author_email' => $com->email_auteur
							, 'comment_date' => $com->date_heure
							, 'comment_author_url' => $com->url_site
							, 'comment_content' => $comtexte
							, 'comment_author_IP' => $com->ip
							, 'comment_type' => ''
							, 'comment_parent' => '0'
							, 'comment_approved' => '1'
							, 'user_ID' => ''
						);						
						//wp_new_comment($data);
						wp_insert_comment($data);
						
						$c++;				
					}
				}
								
			}
			
			//htaccess
			echo '<h3>htaccess</h3>';
			echo '<p>Cut and paste this code at the top of the file</p>';
			echo "<pre>\n";
			echo "# BEGIN spip redirect\n";
			foreach($slugs as $slug){
				echo 'RewriteCond %{QUERY_STRING} '.$slug.' [NC]'."\n";
				echo 'RewriteRule ^(.*)$ %{QUERY_STRING}? [R=301,L]'."\n";
			}
			echo "# END spip redirect\n";
			echo '</pre>';
						
		}
				
		echo '</div>';
	}

	function connect(){
			$this->spip_myid=mysql_connect(SPIP_HOST,SPIP_USER,SPIP_PSW);
			
			if($this->spip_myid==false){
				return false;
			}
			if(mysql_select_db(SPIP_BASE,$this->spip_myid)){
				echo "<p>Connexion ".SPIP_BASE." OK</p>";
				return true;
			}else{
				return false;
			}
	}
	
	function post_format($msg){
		global $wpdb;
		$msg=preg_replace('/\[([^->]*?)\]/is','<em>($1)</em>',$msg);
		$msg=str_replace("{{{","<h3>",$msg);
		$msg=str_replace("}}}","</h3>",$msg);
		$msg=str_replace("{{","<b>",$msg);
		$msg=str_replace("}}","</b>",$msg);
		$msg=str_replace("{","<em>",$msg);
		$msg=str_replace("}","</em>",$msg);
		$msg=str_replace("[[","(<em>",$msg);
		$msg=str_replace("]]","</em>)",$msg);
		$msg=str_replace("<quote>","<blockquote>",$msg);
		$msg=str_replace("</quote>","</blockquote>",$msg);
		$msg=preg_replace('/\[(.*?)->(.*?)\]/is','<a href="$2">$1</a>',$msg);				
		return $wpdb->escape($msg);
	}
	
	function test(){
		$msg="toto plus de liens vers son propre blog. [On peut relever, en effet, que Nicolas Carr vient de publier son livre « The Shallows : What the Internet Is Doing to Our Brains », pour lequel il est actuellement en période de promotion. :o) ] zoro [la conférence Lift10->http://www.liftconference.com/fr/lift-france-10/home_fr] titi ouverture des données publiques (cf. [le programme¤http://www.liftconference.com/toto]). Le thème soulève l’enthous";
		echo($msg."<br><br>");
		$msg=preg_replace('/\[([^->]*?)\]/is','<em>($1)</em>',$msg);
		$msg=preg_replace('/\[(.*?)->(.*?)\]/is','<a href="$2">$1</a>',$msg);				
		echo($msg."<br><br>");
		echo(htmlentities($msg));
	}
}

add_action('admin_menu', 'spip_admin_menu');
function spip_admin_menu() {
	// Add a new submenu under tools
	add_management_page('Spip import','Spip import','edit_themes', basename(__FILE__), 'spip_menu');
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__),"spip_addConfigureLink", 10, 2);
function spip_addConfigureLink($links) { 
	$link = '<a href="tools.php?page=spip_import.php">' . __('Settings') . '</a>';
	array_unshift( $links, $link ); 
	return $links; 
}

function spip_menu(){
	$spip=new spip();
	$spip->menu();
}

?>