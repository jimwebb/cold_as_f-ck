import os, time, json, codecs
import tweepy

# Rough bounding box for USA (captures northern Mexico and southern Canada)
#BOUNDING_BOX = [-125.35,24.55,-66.49,49.27]

# Entire world!
BOUNDING_BOX = [-180,-90,180,90]

# Create the data directory
config = {
    "input_data_directory":"raw_firehose",
    "scrape":{"verbose":False},
}
cmd = "mkdir -p {input_data_directory}"
os.system(cmd.format(**config))


class StdOutListener(tweepy.streaming.StreamListener):
    # Basic stream listener

    def on_data(self, data):
        js = json.loads(data)
        tweet_id = js['id']
        
        # Skip malformed tweets (empty)
        if "text" not in js:
            return True

        # Remove extra padding
        text = ' '.join(js["text"].split())

        # Skip obvious retweets
        if "RT @" == text[:4]:
            return True
        
        # Skip tweets without geo information
        if js["place"] is None:
            return True

        data = {}
        data["place"] = js["place"]

        if "url" in data["place"]:
            data["place"].pop("url")
        
        data["text"] = text

        # Keep the tweet ID
        data['id'] = js['id']

        # Keep the date
        data['created_at'] = js['created_at']

        # Keep user information
        data['user'] = js['user']
                
        js = json.dumps(data)
        
        # Save to a new file every hour
        ts = time.time()
        ts = int(round(ts/3600))

        f_out = "tweets_{}.txt".format(ts)
        f_out = os.path.join(config["input_data_directory"], f_out)
                
        with codecs.open(f_out,'a','utf-8') as FOUT:
            FOUT.write(js+'\n')

        # If verbose, print tweets as them come
        if config["scrape"]["verbose"]:
            print data['place']['full_name'], data['text'] 
            
        
        return True

    def on_error(self, status):
        print "Error", status

if __name__ == "__main__":
    f_access = "access_tokens.json"
    assert(os.path.exists(f_access))
        
    with open(f_access) as FIN:
        cred = json.loads(FIN.read())

    # Handles Twitter authetification & connection to Streaming API
    L = StdOutListener()
    auth = tweepy.OAuthHandler(cred["key"],
                               cred["secret"])

    auth.set_access_token(cred["access"],
                          cred["access_secret"])
    stream = tweepy.Stream(auth, L)

    while True:
        try:
            stream.filter(track=['hot as,hotter than,cold as,colder than,windy as,windier than,rainy as,rainier than'],languages=['en'])
        except:
            print "Stream error"
            time.sleep(10)
