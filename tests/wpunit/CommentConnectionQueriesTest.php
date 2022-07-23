<?php

class CommentConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $created_comment_ids;
	public $current_date_gmt;
	public $current_date;
	public $current_time;
	public $post_id;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->post_id = $this->factory()->post->create();

		$this->current_time        = strtotime( '- 1 day' );
		$this->current_date        = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt    = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin               = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->created_comment_ids = $this->create_comments();
	}

	public function tearDown(): void {
		// then
		parent::tearDown();
	}

	public function createCommentObject( $args = [] ) {

		$post_id = $this->factory()->post->create([
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Post for commenting...',
			'post_author' => $this->admin,
		]);

		/**
		 * Set up the $defaults
		 */
		$defaults = [
			'comment_post_ID'  => $post_id,
			'comment_author'   => $this->admin,
			'comment_content'  => 'Test comment content',
			'comment_approved' => 1,
		];

		/**
		 * Combine the defaults with the $args that were
		 * passed through
		 */
		$args = array_merge( $defaults, $args );

		/**
		 * Create the page
		 */
		$comment_id = $this->factory()->comment->create( $args );

		/**
		 * Return the $id of the comment_object that was created
		 */
		return $comment_id;
	}

	/**
	 * Creates several comments (with different timestamps) for use in pagination tests.
	 *
	 * @return array
	 */
	public function create_comments() {
		// Create 6 comments
		$created_comments = [];
		for ( $i = 1; $i <= 6; $i ++ ) {
			// Set the date 1 minute apart for each post
			$date                   = date( 'Y-m-d H:i:s', strtotime( "-1 day +{$i} minutes" ) );
			$created_comments[ $i ] = $this->createCommentObject(
				[
					'comment_content' => $i,
					'comment_date'    => $date,
				]
			);
		}

		return $created_comments;
	}

	public function getQuery() {
		return '
			query commentsQuery($first:Int $last:Int $after:String $before:String $where:RootQueryToCommentConnectionWhereArgs ){
				comments( first:$first last:$last after:$after before:$before where:$where ) {
					pageInfo {
						hasNextPage
						hasPreviousPage
						startCursor
						endCursor
					}
					edges {
						cursor
						node {
							id
							databaseId
							content
							date
						}
					}
					nodes {
						databaseId
					}
				}
			}
		';
	}

	public function forwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Comment_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge( [
			'first' => 2,
		], $graphql_args );

		// Set the variables to use in the WP query.
		$query_args = array_merge( [
			'comment_status' => 'approved',
			'number'         => 2,
			'offset'         => 0,
			'order'          => 'DESC',
			'orderby'        => 'comment_date',
			'comment_parent' => 0,
		], $query_args );

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['comments']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( 'expected' );
		codecept_debug( $expected );

		$this->markTestIncomplete( 'works until here' );
		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['comments']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasNextPage'] );
	}

	public function backwardPagination( $graphql_args = [], $query_args = [] ) {
		$query    = $this->getQuery();
		$wp_query = new WP_Comment_Query();

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge( [
			'last' => 2,
		], $graphql_args );

		// Set the variables to use in the WP query.
		$query_args = array_merge( [
			'comment_status' => 'approved',
			'number'         => 2,
			'offset'         => 0,
			'order'          => 'ASC',
			'orderby'        => 'comment_date',
			'comment_parent' => 0,
		], $query_args );

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['before'] = '';
		$expected            = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['comments']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 2;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( 'expected' );
		codecept_debug( $expected );

		$this->markTestIncomplete( 'works fine until here' );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['comments']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$query_args['offset'] = 4;

		// Run the GraphQL Query
		$expected = $wp_query->query( $query_args );
		$expected = array_reverse( $expected );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['comments']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['comments']['pageInfo']['hasNextPage'] );
	}

	public function testForwardPagination() {
		$this->forwardPagination();
	}

	public function testBackwardPagination() {
		$this->backwardPagination();
	}


	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		$variables = [
			'first' => 5,
		];

		/**
		 * Test `first`.
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$after_cursor  = $actual['data']['comments']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['comments']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['comments']['nodes'][2];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( $expected );
		$this->markTestIncomplete( 'works until here' );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comments']['nodes'][0] );

		/**
		 * Test `last`.
		 */
		$variables['last'] = 5;

		// Using first and last should throw an error.
		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		unset( $variables['first'] );

		// Get 5 items, but between the bounds of a before and after cursor.
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['comments']['nodes'][0] );

	}

	public function testWhereArgs() {
		$query = $this->getQuery();

		$comment_type_one = 'custom-type-one';
		$comment_type_two = 'custom-type-two';
		$comment_ids      = [
			$this->createCommentObject( [ 'comment_type' => $comment_type_one ] ),
			$this->createCommentObject( [ 'comment_type' => $comment_type_two ] ),
		];

		// test commentType
		$variables = [
			'where' => [
				'commentType' => $comment_type_one,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 1, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][0]['databaseId'] );

		// test commentTypeIn
		$variables = [
			'where' => [
				'commentTypeIn' => [ $comment_type_one, $comment_type_two ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );

		// test commentTypeNotIn
		$variables = [
			'where' => [
				'commentTypeNotIn' => 'comment',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );
		$this->assertCount( 2, $actual['data']['comments']['nodes'] );
		$this->assertEquals( $comment_ids[1], $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $comment_ids[0], $actual['data']['comments']['nodes'][1]['databaseId'] );
	}



	/**
	 * Common asserts for testing pagination.
	 *
	 * @param array $expected An array of the results from WordPress. When testing backwards pagination, the order of this array should be reversed.
	 * @param array $actual The GraphQL results.
	 */
	public function assertValidPagination( $expected, $actual ) {
		$this->assertIsValidQueryResponse( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['comments']['edges'] ) );

		$first_comment  = $expected[0];
		$second_comment = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_comment->comment_ID );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_comment->comment_ID );

		codecept_debug( 'start: ' . $first_comment->comment_ID . ' cursor:' . $start_cursor );
		codecept_debug( 'end: ' . $first_comment->comment_ID . ' cursor:' . $end_cursor );

		$this->assertEquals( $first_comment->comment_ID, $actual['data']['comments']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first_comment->comment_ID, $actual['data']['comments']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['comments']['edges'][0]['cursor'] );
		$this->assertEquals( $second_comment->comment_ID, $actual['data']['comments']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second_comment->comment_ID, $actual['data']['comments']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['comments']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['comments']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['comments']['pageInfo']['endCursor'] );
	}

}
