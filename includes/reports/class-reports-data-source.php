<?php
/**
 * Reports Data Source Helper
 * 
 * Verbindet die Reports-Module mit den indexierten Daten des Plugin-Cores
 * Nutzt network_posts, network_postmeta statt separater Activity-Tabellen
 * 
 * Vorteil: 
 * - Single Source of Truth (nur der Post Index)
 * - Bessere Performance (bereits indexiert)
 * - Konsistente Daten
 * - Keine Duplikation
 */

if ( ! class_exists( 'Reports_Data_Source' ) ) {

    class Reports_Data_Source {

        private $db;
        private $model; // postindexermodel instance

        public function __construct() {
            global $wpdb;
            $this->db = $wpdb;
            
            // Lade das postindexermodel
            $this->load_postindexer_model();
        }

        /**
         * Lade das postindexermodel zur Nutzung der indexierten Daten
         */
        private function load_postindexer_model() {
            if ( ! class_exists( 'postindexermodel' ) ) {
                $model_file = dirname( __FILE__ ) . '/../../classes/class.model.php';
                if ( file_exists( $model_file ) ) {
                    require_once $model_file;
                    $this->model = new postindexermodel();
                }
            } else {
                $this->model = new postindexermodel();
            }
        }

        /**
         * Hole alle Post Types aus den indexierten Netzwerk-Posts
         * 
         * @return array Array von Post Types
         */
        public function get_available_post_types() {
            if ( ! $this->model ) {
                return array( 'post' );
            }

            $post_types = $this->model->get_summary_post_types();
            $types = array();
            
            foreach ( $post_types as $pt ) {
                $types[] = $pt->post_type;
            }
            
            return ! empty( $types ) ? $types : array( 'post' );
        }

        /**
         * Hole alle Blogs die Einträge im Index haben
         * 
         * @return array Array von Blog IDs
         */
        public function get_indexed_blogs() {
            if ( ! $this->model ) {
                return array( get_current_blog_id() );
            }

            $blog_totals = $this->model->get_summary_blog_totals();
            $blogs = array();
            
            foreach ( $blog_totals as $blog ) {
                $blogs[] = $blog->BLOG_ID;
            }
            
            return ! empty( $blogs ) ? $blogs : array( get_current_blog_id() );
        }

        /**
         * Hole alle Authors/Benutzer aus den indexierten Posts
         * 
         * @param string $search Optional: Suchterm für Autocomplete
         * @param int $limit Limit für Ergebnisse
         * @return array Array von Benutzernamen
         */
        public function get_indexed_users( $search = '', $limit = 20 ) {
            if ( ! $this->model ) {
                return array();
            }

            // Hole unique post_author IDs aus network_posts
            $sql = "SELECT DISTINCT np.post_author 
                    FROM {$this->model->network_posts} np
                    WHERE np.post_author > 0";
            
            $author_ids = $this->db->get_col( $sql );
            
            if ( empty( $author_ids ) ) {
                return array();
            }

            // Konvertiere IDs zu User Objects
            $users = array();
            foreach ( $author_ids as $user_id ) {
                $user = get_user_by( 'id', $user_id );
                if ( $user ) {
                    // Filter nach Suchbegriff wenn angegeben
                    if ( ! empty( $search ) ) {
                        if ( stripos( $user->user_login, $search ) === false && 
                             stripos( $user->display_name, $search ) === false ) {
                            continue;
                        }
                    }
                    
                    $users[] = $user->user_login;
                    
                    if ( count( $users ) >= $limit ) {
                        break;
                    }
                }
            }

            return $users;
        }

        /**
         * Hole Posts für einen Benutzer aus dem Index
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage zurück
         * @param string $post_type Post Type Filter
         * @return array Array von Posts
         */
        public function get_user_posts( $user_id, $days = 30, $post_type = 'post' ) {
            if ( ! $this->model ) {
                return array();
            }

            $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

            $sql = $this->db->prepare(
                "SELECT * FROM {$this->model->network_posts} 
                 WHERE post_author = %d 
                 AND post_modified_gmt >= %s
                 AND post_type = %s
                 AND post_status = 'publish'
                 ORDER BY post_modified_gmt DESC",
                $user_id,
                $date_limit,
                $post_type
            );

            return $this->db->get_results( $sql, ARRAY_A );
        }

        /**
         * Hole Pages für einen Benutzer aus dem Index
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage zurück
         * @return array Array von Pages
         */
        public function get_user_pages( $user_id, $days = 30 ) {
            return $this->get_user_posts( $user_id, $days, 'page' );
        }

        /**
         * Hole Post-Zähler pro Tag für einen Benutzer
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage
         * @param string $post_type Post Type
         * @return array Array mit Datums-Keys und Zählwert-Values
         */
        public function get_user_posts_by_date( $user_id, $days = 30, $post_type = 'post' ) {
            if ( ! $this->model ) {
                return array();
            }

            $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

            $sql = $this->db->prepare(
                "SELECT DATE(post_modified_gmt) as post_date, COUNT(*) as post_count
                 FROM {$this->model->network_posts}
                 WHERE post_author = %d
                 AND post_modified_gmt >= %s
                 AND post_type = %s
                 AND post_status = 'publish'
                 GROUP BY DATE(post_modified_gmt)
                 ORDER BY post_date DESC",
                $user_id,
                $date_limit,
                $post_type
            );

            $results = $this->db->get_results( $sql, ARRAY_A );
            
            // Konvertiere zu assoc array [date => count]
            $data = array();
            foreach ( $results as $row ) {
                $data[ $row['post_date'] ] = intval( $row['post_count'] );
            }

            return $data;
        }

        /**
         * Hole Post-Zähler pro Blog
         * 
         * @param int $limit Limit für Top Blogs
         * @return array Array mit blog_id Keys und post_count Values
         */
        public function get_posts_by_blog( $limit = 15 ) {
            if ( ! $this->model ) {
                return array();
            }

            $results = $this->model->get_summary_blog_totals();
            
            $data = array();
            foreach ( $results as $row ) {
                $data[ $row->BLOG_ID ] = intval( $row->blog_count );
            }

            return $data;
        }

        /**
         * Hole Post-Typen pro Blog
         * 
         * @param int $limit Limit für Top Blogs
         * @return array Multidimensionales Array [blog_id => [post_type => count]]
         */
        public function get_post_types_by_blog( $limit = 15 ) {
            if ( ! $this->model ) {
                return array();
            }

            $results = $this->model->get_summary_blog_post_type_totals();
            
            $data = array();
            foreach ( $results as $row ) {
                if ( ! isset( $data[ $row->BLOG_ID ] ) ) {
                    $data[ $row->BLOG_ID ] = array();
                }
                $data[ $row->BLOG_ID ][ $row->post_type ] = intval( $row->blog_type_count );
            }

            return $data;
        }

        /**
         * Hole Comments für einen Benutzer (über postmeta oder Comments-Hook)
         * 
         * HINWEIS: Comments sind komplizierter da sie nicht zentral indexiert sind
         * Daher durchsuchen wir individual Blog Comments
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage zurück
         * @return array Array von Comments
         */
        public function get_user_comments( $user_id, $days = 30 ) {
            $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

            // Durchsuche alle Blogs für Comments des Users
            $blogs = $this->get_indexed_blogs();
            $all_comments = array();

            foreach ( $blogs as $blog_id ) {
                switch_to_blog( $blog_id );

                $comments = get_comments( array(
                    'user_id' => $user_id,
                    'date_query' => array(
                        'after' => $date_limit,
                    ),
                    'orderby' => 'comment_date_gmt',
                    'order' => 'DESC',
                ) );

                foreach ( $comments as $comment ) {
                    $comment->blog_id = $blog_id;
                    $all_comments[] = $comment;
                }

                restore_current_blog();
            }

            return $all_comments;
        }

        /**
         * Hole Comment-Zähler pro Tag für einen Benutzer
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage
         * @return array Array mit Datums-Keys und Zählwert-Values
         */
        public function get_user_comments_by_date( $user_id, $days = 30 ) {
            $comments = $this->get_user_comments( $user_id, $days );
            
            $data = array();
            foreach ( $comments as $comment ) {
                $date = date( 'Y-m-d', strtotime( $comment->comment_date_gmt ) );
                if ( ! isset( $data[ $date ] ) ) {
                    $data[ $date ] = 0;
                }
                $data[ $date ]++;
            }

            return $data;
        }

        /**
         * Hole Post-Zähler pro Tag für einen Benutzer
         * Für Chart-Darstellung in Reports
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage zurück
         * @param string $post_type Post Type (post, page, etc.)
         * @return array Array mit Datums-Keys (Y-m-d) und Zählwert-Values
         */
        public function get_user_posts_by_date( $user_id, $days = 30, $post_type = 'post' ) {
            if ( ! $this->model ) {
                return array();
            }

            $data = array();
            $date_limit = date( 'Y-m-d', strtotime( "-{$days} days" ) );

            // Nutze das Model um alle Posts des Users zu holen
            $posts = $this->get_user_posts( $user_id, $days, $post_type );
            
            // Aggregiere nach Datum
            foreach ( $posts as $post ) {
                $date = date( 'Y-m-d', strtotime( $post->post_date_gmt ) );
                if ( ! isset( $data[ $date ] ) ) {
                    $data[ $date ] = 0;
                }
                $data[ $date ]++;
            }

            return $data;
        }

        /**
         * Hole Page-Zähler pro Tag für einen Benutzer
         * Alias für get_user_posts mit post_type='page'
         * 
         * @param int $user_id WordPress User ID
         * @param int $days Anzahl der Tage zurück
         * @return array Array mit Datums-Keys (Y-m-d) und Zählwert-Values
         */
        public function get_user_pages_by_date( $user_id, $days = 30 ) {
            return $this->get_user_posts_by_date( $user_id, $days, 'page' );
        }

        /**
         * Gibt den aktuellen Model zurück für direkte Nutzung
         */
        public function get_model() {
            return $this->model;
        }
    }
}
