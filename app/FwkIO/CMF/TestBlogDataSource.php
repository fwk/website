<?php
namespace FwkIO\CMF;

use Nitronet\Fwk\CMF\DataSource;

class TestBlogDataSource implements DataSource
{
    public function fetch(array $options = array())
    {
        $content = <<<EOF
<h4>Some intro for this article, because sometimes this is helpfull and this way I can test the design...</h4>
<p>This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks. This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks. This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks. This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks. This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks. This is an <b>ordinary paragraph</b> that is <i>long enough</i> to wrap to <u>multiple lines</u> so that you can see how the line spacing looks.</p>
EOF;
        return array(
            'one'   => array(
                'id'    => 1,
                'title' => 'Article title',
                'slug'  => 'article-title',
                'date'  => '2014-01-02 23:12:56',
                'category' => 'Fwk',
                'content' => $content
            ),
            'two'   => array(
                'id'    => 2,
                'title' => 'Other article',
                'slug'  => 'other-article',
                'date'  => '2014-01-02 23:12:56',
                'category' => 'General',
                'content' => $content
            )
        );
    }
    
    public function one($slug)
    {
        $results = $this->fetch();
        foreach ($results as $article) {
            if ($article['slug'] == $slug) {
                return array($article);
            }
        }
        
        return array();
    }
    
    public function categories()
    {
        return array(
            1 => 'General',
            2 => 'Fwk',
            3 => 'PHP',
            4 => 'How-to\'s',
            5 => 'Still life'
        );
    }
}

