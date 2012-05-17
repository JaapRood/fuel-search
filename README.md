# Search for FuelPHP
### Search is a FuelPHP package to search through data

* [Jaap Rood](http://www.jaaprood.nl)
* [Ronald Mansveld](https://twitter.com/RomoLovesYou)

## Slightly advanced, easy to use, search
We find that implementing search into apps and websites can be quite a pain. MySQL doesn't support much, Google Site Search is far from ideal (and often not accepted by clients) and not everyone can run or has the time to work with [Apache Lucence](http://lucence.apache.org). We want search that is **easy to use, pretty quick and supports spelling mistakes**. To do this, we implemented a variation of the [Damerau-Levenshtein algorithm](http://en.wikipedia.org/wiki/Damerau%E2%80%93Levenshtein_distance) to calculate the similarity of words.

Make no mistake, in the search world this stuff is pretty basic, and the solution is far from ideal. But we believe it's a pretty cool alternative for other solutions and has come quite handy a number of times already.

# Usage

Searching through data is pretty easy. All you need is the data, the field(s) you want to look in and the term you want to search for.

```php
$products = Model_Product::find('all');

$found_products = Search::find('shampoo')
	->in($products) // the data to search through
	->by('name', 'slogan') // the fields you wan't to search through
	->execute();
```

As data, an array with either objects or arrays is accepted. There are some other cool ways to tweak your results

```php
$posts = Model_Post:find('all');

$found_posts = Search::find('foobar')
	->in($posts)
	->by('title', 'subtitle', 'slug')
	->relevance(80) // this means the matched words in the data must be about 80% the same
	->limit(10)	// let's limit the amount of results to 10
	->offset(5) // offset the results, handy for pagination
	->execute();

```

# Known issues and stuff we're working on
This class does not work properly with multiple word search terms. We're aware of this and are working on a solution. Also, because it looks at every single word, it's not that efficient on big text fields, we recommend using it on stuff like titles, names, etc.

__Update:__ we've made improvements to the way multiple word search terms are handled in the master branch. It's still not quite where we'd like it to be, but it's a lot better than before.

Next to improving results we've got heaps of ideas on stuff we could incorporate, like skipping common words, built in caching and searching in nested arrays / objects. We really want to know what you think would be useful! We will love you if you whip it all up and do a pull request :)

We will use Github's issue tracker to keep track of all issues,	bugs, new ideas, etc. 