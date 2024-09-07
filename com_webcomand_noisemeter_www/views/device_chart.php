        <div id="chart-<?= $device_uuid ?>" class="plotly-graph-div"></div>
        <script type="text/javascript">
            window.PLOTLYENV=window.PLOTLYENV || {};
    		Plotly.newPlot("chart-<?= $device_uuid ?>",
    			[
    				{
    					"line":{"color":"RGBA(253, 203, 128, 1)"},
    					"mode":"lines",
    					"name":"min",
                        "x":[<?= $timestamps ?>],
                        "y":[<?= $min_values ?>],
                        "type":"scatter"
    				},{
        			    "fill":"tonexty","fillcolor":"RGBA(253, 203, 128, 0.2)",
        				"line":{"color":"RGBA(253, 203, 128, 1)"},
    					"mode":"lines",
    					"name":"max",
    					"x":[<?= $timestamps ?>],
                        "y":[<?= $max_values ?>],
                        "type":"scatter"
    				},{
        				"line":{"color":"RGBA(226, 81, 0, 1)", "width": 6},
    					"mode":"lines",
    					"name":"mean",
                        "x":[<?= $timestamps ?>],
                        "y":[<?= $mean_values ?>],
                        "type":"scatter"
    				}
<?php if(isset($outlier_sizes) && isset($outlier_timestamps) && isset($outlier_values)): ?>
                    ,{
        			    "marker":{
        					"color":"RGBA(255, 0, 0, 0.5)",
    						"size":[<?= $outlier_sizes ?>]
				        },
    					"mode":"markers",
    					"name":"outlier",
                        "x":[<?= $outlier_timestamps ?>],
                        "y":[<?= $outlier_values ?>],
                        "type":"scatter"
    				}
<?php endif; ?>
                ],
                {
        			"template":{
    					"data":{},
    					"layout":{
    						"autotypenumbers":"strict",
    						//"colorway":["#636efa","#EF553B","#00cc96","#ab63fa","#FFA15A","#19d3f3","#FF6692","#B6E880","#FF97FF","#FECB52"],
    						"font":{"color":"#2a3f5f"},
    						"hovermode":"x unified",
    						"hoverlabel":{"align":"left"},
    						"paper_bgcolor":"#FFF9F0",
    						"plot_bgcolor":"#FFF9F0",
    						//"polar":{"bgcolor":"#E5ECF6","angularaxis":{"gridcolor":"white","linecolor":"white","ticks":""},"radialaxis":{"gridcolor":"white","linecolor":"white","ticks":""}},
    						//"ternary":{"bgcolor":"#E5ECF6","aaxis":{"gridcolor":"white","linecolor":"white","ticks":""},"baxis":{"gridcolor":"white","linecolor":"white","ticks":""},"caxis":{"gridcolor":"white","linecolor":"white","ticks":""}},
    						"coloraxis":{"colorbar":{"outlinewidth":0,"ticks":""}},
    						"colorscale":{"sequential":[[0.0,"#0d0887"],[0.1111111111111111,"#46039f"],[0.2222222222222222,"#7201a8"],[0.3333333333333333,"#9c179e"],[0.4444444444444444,"#bd3786"],[0.5555555555555556,"#d8576b"],[0.6666666666666666,"#ed7953"],[0.7777777777777778,"#fb9f3a"],[0.8888888888888888,"#fdca26"],[1.0,"#f0f921"]],"sequentialminus":[[0.0,"#0d0887"],[0.1111111111111111,"#46039f"],[0.2222222222222222,"#7201a8"],[0.3333333333333333,"#9c179e"],[0.4444444444444444,"#bd3786"],[0.5555555555555556,"#d8576b"],[0.6666666666666666,"#ed7953"],[0.7777777777777778,"#fb9f3a"],[0.8888888888888888,"#fdca26"],[1.0,"#f0f921"]],"diverging":[[0,"#8e0152"],[0.1,"#c51b7d"],[0.2,"#de77ae"],[0.3,"#f1b6da"],[0.4,"#fde0ef"],[0.5,"#f7f7f7"],[0.6,"#e6f5d0"],[0.7,"#b8e186"],[0.8,"#7fbc41"],[0.9,"#4d9221"],[1,"#276419"]]},
    						//"xaxis":{"gridcolor":"white","linecolor":"white","ticks":"","title":{"standoff":15},"zerolinecolor":"white","automargin":true,"zerolinewidth":2},
    						//"yaxis":{"gridcolor":"white","linecolor":"white","ticks":"","title":{"standoff":15},"zerolinecolor":"white","automargin":true,"zerolinewidth":2},
    						//"scene":{"xaxis":{"backgroundcolor":"#E5ECF6","gridcolor":"white","linecolor":"white","showbackground":true,"ticks":"","zerolinecolor":"white","gridwidth":2},"yaxis":{"backgroundcolor":"#E5ECF6","gridcolor":"white","linecolor":"white","showbackground":true,"ticks":"","zerolinecolor":"white","gridwidth":2},"zaxis":{"backgroundcolor":"#E5ECF6","gridcolor":"white","linecolor":"white","showbackground":true,"ticks":"","zerolinecolor":"white","gridwidth":2}},
    						//"shapedefaults":{"line":{"color":"#2a3f5f"}},
    						//"annotationdefaults":{"arrowcolor":"#2a3f5f","arrowhead":0,"arrowwidth":1},
    						//"geo":{"bgcolor":"white","landcolor":"#E5ECF6","subunitcolor":"white","showland":true,"showlakes":true,"lakecolor":"white"},
    						//"title":{"x":0.05},
                            "margin": {l: 50, r: 0, b: 50, t: 20, pad: 4},
    						"mapbox":{"style":"light"}
    					}
    				},
    				//"xaxis":{"rangeslider":{"visible":true}},
    				"yaxis":{"title":{"text":"Noise Level (dBA)"}},
    				"showlegend":false,
    				//"title":{"text":"Noise Meter <?= $device->DeviceID ?>"}
    			},
    			{"responsive": true}
    		);
        </script>
