import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';

// DEV USE:
// THIS PAGE IS FOR DEVELOPER USE TO TEST NEW FEATURES
// DELETE THIS PAGE AND ROUTE BEFORE MOVING TO PRODUCTION!

export function TestPage() { 
    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Test Page";
        log('Rendered Test page (pages/TestPage.jsx)');
    }, []);

    function checkIMDB() {
         window.open('https://www.imdb.com/title/tt0039628','checkWindow','width=640,height=480');
    }

    function showModalList() {
        alert('This is where a modal would appear showing the missing thumbnails list');
    }

    return (
    <>

        <h2 className="my-4 text-center">Thumbnail Manager</h2>

        <div className="row m-2 mx-auto p-3 align-items-end rounded-3 border border-1 border-tertiary" style="width: 90%;">
            <div className="col-3">
                <ul className="list-group list-group-flush ms-3 mb-3">
                    <li className="list-group-item">Number of shows: 127</li>
                    <li className="list-group-item">Number of thumbnails: 120</li>
                    <a href="#" onClick={showModalList} className="list-group-item list-group-item-action">Missing Thumbnails: 7</a>
                </ul>
            </div>
            <div className="col-9">

                {/* ALERTS & ERRORS --- hidden by default */}

                <div id="thumbSuccess" className="d-none alert alert-success alert-dismissible fade show" role="alert">
                    This is a success message!
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div id="thumbError" className="alert alert-danger alert-dismissible fade show" role="alert">
                    This is an error message!
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>                  

                {/* Search by Title */}
                <div className="input-group mb-3 mx-auto">
                    <input type="text" className="form-control" placeholder="Search for Thumbnail Image by Show Title" value="" /> 
                    <button className="btn btn-outline-secondary" type="button" id="searchBtn">Search</button>
                </div>
            </div>
        </div>      

        {/* Row */}
        <div className="row m-2 mb-5 mx-auto p-3 rounded-3 border border-1 border-dark" style="width: 90%;">

            {/* Left Column */}
            <div className="col-2">

                {/* Thumbnail Selector */}
                <select className="form-select" multiple style="width: 200px; height: 500px;">
                    <option value="tt0039628.jpg" selected>tt0039628.jpg</option>
                    <option value="tt0041038.jpg">tt0041038.jpg</option>
                    <option value="tt0043225.jpg">tt0043225.jpg</option>
                    <option value="tt0044229.jpg">tt0044229.jpg</option>
                    <option value="tt0044231.jpg">tt0044231.jpg</option>
                    <option value="tt0046578.jpg">tt0046578.jpg</option>
                    <option value="tt0046593.jpg">tt0046593.jpg</option>
                    <option value="tt0046642.jpg">tt0046642.jpg</option>
                    <option value="tt0051327.jpg">tt0051327.jpg</option>
                    <option value="tt0052451.jpg">tt0052451.jpg</option>
                    <option value="tt0052507.jpg">tt0052507.jpg</option>
                    <option value="tt0052520.jpg">tt0052520.jpg</option>
                    <option value="tt0054572.jpg">tt0054572.jpg</option>
                    <option value="tt0055683.jpg">tt0055683.jpg</option>
                    <option value="tt0055686.jpg">tt0055686.jpg</option>
                    <option value="tt0056751.jpg">tt0056751.jpg</option>
                    <option value="tt0056757.jpg">tt0056757.jpg</option>
                    <option value="tt0056780.jpg">tt0056780.jpg</option>
                    <option value="tt0057193.jpg">tt0057193.jpg</option>
                    <option value="tt0057753.jpg">tt0057753.jpg</option>
                </select>

            </div>

            {/* Center Column */}
            <div className="col-1">

            </div>

            {/* Right Column */}
            <div className="col-9 p-2">

                {/* Right Column (Main Content Area) */}
                <div className="row p-2">

                    {/* Form */}
                    <div className="col">

                        
                        <label htmlFor="title" className="form-label fw-bold">Show Title</label>

                        <div className="mb-3">
                            <input type="text" className="form-control" id="title" value="Miracle on 34th Street" style="width: 200px;" readOnly disabled />
                        </div>

                        <label htmlFor="imdb" className="form-label fw-bold">IMDB ID</label>

                        <div className="input-group mb-3">
                            <input type="text" className="form-control" id="imdb" value="tt0039628" style="width: 200px;" readOnly disabled />
                            <button className="btn btn-sm btn-secondary" onClick={checkIMDB} title="Check IMDB Page">Check IMDB Page<img src="/src/assets/external-link-white.svg" className="ms-2 pb-1" height="20" /></button>
                        </div>

                        <label htmlFor="playlist" className="form-label fw-bold">Used by Playlist</label>

                        <div className="w-75">
                            <input type="text" className="form-control" id="playlist" value="freetv.json" style="width: 200px;" readOnly disabled />
                        </div>

                        <div className="my-4 fw-bold">Fetch New Thumbnail from IMDB</div>

                        <div>
                            <button className="btn btn-warning me-2">Re-fetch Thumbnail</button>
                            <button className="btn btn-primary disabled">Save File</button>
                        </div>

                        <div className="my-4 fw-bold">Upload A New Thumbnail</div>

                        <div className="mb-5">
                            <input type="file" className="form-control mb-3"/> 
                            <button className="btn btn-sm btn-outline-secondary" type="button" id="newThumb">Upload File</button>
                            <button className="btn btn-sm btn-primary disabled ms-2">Save File</button>
                        </div>

                    </div>

                    {/* Thumbnail Preview */}
                    <div className="col text-center">
                        <figure className="figure">
                        {/* <img id="thumbPreview" src="/src/assets/vintage-tv.png" height="350" style="border: 2px dashed black; border-radius: 12px;" /> */}
                        <img id="thumbPreview" src="/thumbs/tt0039628.jpg" height="350" style="border: 2px dashed black; border-radius: 12px;" />
                        <figcaption className="mt-2 figure-caption">Thumbnail Image Preview</figcaption>
                        </figure>
                    </div>

                </div>

            </div>
        </div>
    </>    
    );
}