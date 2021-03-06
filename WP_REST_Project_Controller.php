<?php

class WP_REST_Project_Controller extends WP_REST_Posts_Controller {

    /**
     * The namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The post type for the current object.
     *
     * @var string
     */
    protected $post_type;

    /**
     * Rest base for the current object.
     *
     * @var string
     */
    protected $rest_base;

    /**
     * Register the routes for the objects of the controller.
     * Nearly the same as WP_REST_Posts_Controller::register_routes(), but with a
     * custom permission callback.
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_collection_params(),
                'show_in_index'       => true,
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                'show_in_index'       => true,
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/back_project', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'backProjectApiCallback'),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args' => array(
                    'project_id' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    ),
                    'amount' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/get_balance', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getBalance'),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/get_transactions', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'getTransactions'),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );

        /**
         * endpoint for sending coins to all accounts
         */
        register_rest_route( $this->namespace, '/setCoinsToAll', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'setCoinsToAll'),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args' => array(
                    'amount' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric( $param );
                        }
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => array(
                    'context' => $this->get_context_param( array( 'default' => 'view' ) ),
                ),
                'show_in_index'       => true,
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
                'show_in_index'       => true,
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'                => array(
                    'force' => array(
                        'default'     => true,
                        'description' => __( 'Whether to bypass trash and force deletion.' ),
                    ),
                ),
                'show_in_index'       => false,
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    public function backProjectApiCallback(WP_REST_Request $request) {

        $params = $request->get_body();
        $params = json_decode($params);
        $user = wp_get_current_user();

        $projectId = (int)$params->project_id;
        $amount = (int)$params->amount;
        $userId = (int) $user->ID;

        $return = [
            'status' => 'success',
        ];

        $balance = SJProjectsApi::getAccountBalance($userId);
        $project = SJProjectsApi::getProject($projectId);
        $projectPledgeSum = SJProjectsApi::getProjectPledgeSum($projectId);
        $canPledge = $project->price - $projectPledgeSum;

        if ($balance->amount < $amount) {
            $return['status'] = 'error';
            $return['message'] = 'Not enough coins';
        } elseif ((int)$project->price > 0 && $canPledge < $amount && !$project->canDonateMore) {
            $return['status'] = 'error';
            $return['message'] = 'Too much, try to pledge ' . $canPledge . ' coins';
        } else {
            $return['amount'] = $amount;
            SJProjectsApi::backProject($userId, $projectId, $amount);
        }

        $newProject = SJProjectsApi::getProject($projectId);
        $projectPledgeSum = SJProjectsApi::getProjectPledgeSum($projectId);

        if($newProject->price <= $projectPledgeSum) {
            SJProjectsApi::updateProjectStatus($projectId, 'founded');
            SJProjectsApi::updateProjectTransactionsStatus($projectId, 'founded');
        }



        $response = rest_ensure_response( $return );

        return $response;
    }

    public function getBalance() {
        $user = wp_get_current_user();
        return SJProjectsApi::getAccountBalance($user->ID);
    }

    public function getTransactions() {
        $user = wp_get_current_user();
        return SJProjectsApi::getAccountTransactions($user->ID);
    }

    /**
     * Check if a given request has access to get items
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function setCoinsToAll(WP_REST_Request $request) {
        if(!current_user_can('administrator')) {
            return 'error';
        }

        $amount = $request->get_param('amount');
        return SJProjectsApi::setCoinsToAll((int)$amount);
    }

}