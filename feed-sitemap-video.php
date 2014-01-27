<?php
/**
 * XML Sitemap Feed Template for displaying an XML Sitemap feed.
 *
 * @package Google Video Sitemap Feed With Multisite Support plugin for WordPress
 */

set_time_limit(300);
//error_reporting(E_ALL);
//ini_set("display_errors", 1);

//Procesa correctamente las entidades del RSS
$entity_custom_from = false; 
$entity_custom_to = false;

function sitemap_video_html_entity($data) {
	global $entity_custom_from, $entity_custom_to;
	
	if(!is_array($entity_custom_from) || !is_array($entity_custom_to)) {
		$array_position = 0;
		foreach (get_html_translation_table(HTML_ENTITIES) as $key => $value) {
			switch ($value) {
				case '&nbsp;':
					break;
				case '&gt;':
				case '&lt;':
				case '&quot;':
				case '&apos;':
				case '&amp;':
					$entity_custom_from[$array_position] = $key; 
					$entity_custom_to[$array_position] = $value; 
					$array_position++; 
					break; 
				default: 
					$entity_custom_from[$array_position] = $value; 
					$entity_custom_to[$array_position] = $key; 
					$array_position++; 
			} 
		}
	}
	return str_replace($entity_custom_from, $entity_custom_to, $data); 
}

//Obtiene información del vídeo
//function informacion_del_video($identificador) {
function get_youtube_information($identificador) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://gdata.youtube.com/feeds/api/videos/$identificador");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);
    if($data == 'Video not found' OR $data == 'Invalid id')
        return false;
    $data = simplexml_load_string($data);
	return $data;
}

function get_dailymotion_information($identificador) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.dailymotion.com/video/$identificador");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = json_decode(curl_exec($ch));
    curl_close($ch);
    return $data;
}

function get_vimeo_information($identificador) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://vimeo.com/api/v2/video/$identificador.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = json_decode(curl_exec($ch));
    curl_close($ch);
    return $data[0];
}

function get_video_information($identificador, $provider) {
    switch ($provider) {
        case 'youtube':
            return get_youtube_information($identificador);
            break;
        case 'dailymotion':
            return get_dailymotion_information($identificador);
            break;
        case 'vimeo':
            return get_vimeo_information($identificador);
            break;
    }
    return '';
}

status_header('200'); // force header('HTTP/1.1 200 OK') for sites without posts
header('Content-Type: text/xml; charset=' . get_bloginfo('charset'), true);

echo '<?xml version="1.0" encoding="' . get_bloginfo('charset') . '"?>
<!-- Created by Google Video Sitemap Feed With Multisite Support by Art Project Group (http://www.artprojectgroup.es/plugins-para-wordpress/google-video-sitemap-feed-with-multisite-support) -->
<!-- Generated-on="' . date('Y-m-d\TH:i:s+00:00') . '" -->
<?xml-stylesheet type="text/xsl" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/google-video-sitemap-feed-with-multisite-support/video-sitemap.xsl"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . PHP_EOL;

$entradas = $wpdb->get_results ("(SELECT id, post_title, post_content, post_date, post_excerpt
                                    FROM $wpdb->posts
                                    WHERE post_status = 'publish'
                                        AND (post_type = 'post' OR post_type = 'page')
                                        AND (post_content LIKE '%youtube.com%'
                                            OR post_content LIKE '%youtube-nocookie.com%'
                                            OR post_content LIKE '%dailymotion.com%'
                                            OR post_content LIKE '%vimeo.com%'))
                                UNION ALL
                                    (SELECT id, post_title, meta_value as 'post_content', post_date, post_excerpt
                                        FROM $wpdb->posts
                                        JOIN $wpdb->postmeta
                                            ON id = post_id
                                                AND meta_key = 'wpex_post_oembed'
                                                AND (meta_value LIKE '%youtube.com%'
                                                    OR meta_value LIKE '%youtube-nocookie.com%'
                                                    OR meta_value LIKE '%dailymotion.com%'
                                                    OR meta_value LIKE '%vimeo.com%')
                                        WHERE post_status = 'publish'
                                            AND (post_type = 'post' OR post_type = 'page'))
                                ORDER BY post_date DESC");

global $wp_query;
$wp_query->is_404 = false;	// force is_404() condition to false when on site without posts
$wp_query->is_feed = true;	// force is_feed() condition to true so WP Super Cache includes the sitemap in its feeds cache

if (!empty($entradas)) 
{
	$videos = array();
    $done = array();
	foreach ($entradas as $entrada) 
	{
		setup_postdata($entrada);

        if (preg_match_all ('/youtube.com\/(v\/|watch\?v=|embed\/)([^\$][a-zA-Z0-9\-_]*)/', $entrada->post_content, $matches, PREG_SET_ORDER)
            OR preg_match_all ('/youtube-nocookie.com\/(v\/|watch\?v=|embed\/)([^\$][a-zA-Z0-9\-_]*)/', $entrada->post_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $videos[] = array('provider'=> 'youtube',
                    'id' => $match[2],
                    'player' => "http://youtube.googleapis.com/v/$match[2]",
                    'thumbnail'  => "http://i.ytimg.com/vi/$match[2]/hqdefault.jpg");
            }
        }
        if (preg_match_all ('/dailymotion.com\/(video\/)([^\$][a-zA-Z0-9]*)/', $entrada->post_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $videos[] = array('provider'=> 'dailymotion',
                    'id' => $match[2],
                    'player' => "http://www.dailymotion.com/embed/video/$match[2]",
                    'thumbnail'  => "http://www.dailymotion.com/thumbnail/video/$match[2]");
            }
        }
        if (preg_match_all ('/vimeo.com\/([^\$][0-9]*)/', $entrada->post_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $videos[] = array('provider'=> 'vimeo',
                    'id' => $match[1],
                    'player' => "http://player.vimeo.com/video/$match[1]");
            }
        }

        if (!empty($videos))
		{
			$extracto = ($entrada->post_excerpt != "") ? $entrada->post_excerpt : get_the_excerpt(); 
			$enlace = htmlspecialchars(get_permalink($entrada->id));
			$contador = 0;
			$multiple = false;
	
			foreach ($videos as $video) 
			{
                if (in_array($video['id'], $done)) continue;
                array_push($done, $video['id']);

				if ($contador > 0) $multiple = true;
				if ($multiple) 
				{
                    $info = get_video_information($video['id'], $video['provider']);
                    if (!$info) continue;
                    $titulo = $info->title;
					$descripcion = $titulo . ". " . $extracto;
                    if ($video['provider'] == 'vimeo')
                        $video['thumbnail'] = $info->thumbnail_large;
				}
				else 
				{
					$titulo = $entrada->post_title;
					$descripcion = $extracto;
                    $post_thumbnail = wp_get_attachment_url( get_post_thumbnail_id($entrada->id) );
                    if (!empty($post_thumbnail)) {
                        $video['thumbnail'] = $post_thumbnail;
                    } elseif ($video['provider'] == 'vimeo') {
                        $info = get_video_information($video['id'], $video['provider']);
                        $video['thumbnail'] = $info->thumbnail_large;
                    }
				}
				$contador++;
				
				echo "\t" . '<url>' . PHP_EOL;
				echo "\t\t" . '<loc>' . $enlace . '</loc>' . PHP_EOL;
				echo "\t\t" . '<video:video>' . PHP_EOL;
				echo "\t\t" . '<video:player_loc allow_embed="yes" autoplay="autoplay=1">' . $video['player'] . '</video:player_loc>' . PHP_EOL;
				echo "\t\t" . '<video:thumbnail_loc>'. $video['thumbnail'] .'</video:thumbnail_loc>' . PHP_EOL;
				echo "\t\t" . '<video:title>' . sitemap_video_html_entity(html_entity_decode($titulo, ENT_QUOTES, 'UTF-8')) . '</video:title>' . PHP_EOL;
				echo "\t\t" . '<video:description>' . sitemap_video_html_entity(html_entity_decode($descripcion, ENT_QUOTES, 'UTF-8')) . '</video:description>' . PHP_EOL;
    
				$etiquetas = get_the_tags($entrada->id); 
				if ($etiquetas) 
				{ 
                	$numero_de_etiquetas = 0;
                	foreach ($etiquetas as $etiqueta) 
					{
                		if ($numero_de_etiquetas++ > 32) break;
                		echo "\t\t" . '<video:tag>' . $etiqueta->name . '</video:tag>' . PHP_EOL;
                	}
				}    

				$categorias = get_the_category($entrada->id); 
				if ($categorias) 
				{ 
                	foreach ($categorias as $categoria) 
					{
                		echo "\t\t" . '<video:category>' . $categoria->name . '</video:category>' . PHP_EOL;
                		break;
                	}
				}        
				echo "\t\t" . '</video:video>' . PHP_EOL;
				echo "\t" . '</url>' . PHP_EOL;
			}
		}
	}
}
echo "</urlset>";
?>
