import React, { useEffect, useRef, useState } from "react";
import { View, Button, ScrollView, ImageBackground, Dimensions } from "react-native";
import { captureRef } from "react-native-view-shot";
import fabric from "fabric";

export default function CertificateEditor() {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const fabricRef = useRef<fabric.Canvas | null>(null);
  const [canvasSize, setCanvasSize] = useState({
    width: Dimensions.get("window").width * 0.8,
    height: Dimensions.get("window").height * 0.7,
  });

  // Inicializa Fabric.js apenas uma vez
  useEffect(() => {
    const initCanvas = () => {
      const canvas = new fabric.Canvas("certificateCanvas", {
        backgroundColor: "#fff",
        preserveObjectStacking: true,
      });

      fabricRef.current = canvas;
      resizeCanvas();

      // Redimensiona ao mudar tamanho da janela
      const updateSize = () => resizeCanvas();
      window.addEventListener("resize", updateSize);

      return () => {
        window.removeEventListener("resize", updateSize);
        canvas.dispose();
      };
    };

    initCanvas();
  }, []);

  const resizeCanvas = () => {
    if (fabricRef.current) {
      const w = window.innerWidth * 0.8;
      const h = window.innerHeight * 0.7;
      fabricRef.current.setWidth(w);
      fabricRef.current.setHeight(h);
      setCanvasSize({ width: w, height: h });
      fabricRef.current.renderAll();
    }
  };

  const centerPosition = (obj) => {
    const canvas = fabricRef.current;
    if (!canvas) return;
    const center = canvas.getCenter();
    obj.set({ left: center.left - obj.width / 2, top: center.top - obj.height / 2 });
  };

  const addRectangle = () => {
    const rect = new fabric.Rect({
      width: 150,
      height: 100,
      fill: "lightblue",
      stroke: "black",
      strokeWidth: 2,
    });
    centerPosition(rect);
    fabricRef.current.add(rect);
  };

  const addCircle = () => {
    const circle = new fabric.Circle({
      radius: 60,
      fill: "lightgreen",
      stroke: "black",
      strokeWidth: 2,
    });
    centerPosition(circle);
    fabricRef.current.add(circle);
  };

  const addTriangle = () => {
    const triangle = new fabric.Triangle({
      width: 150,
      height: 120,
      fill: "orange",
      stroke: "black",
      strokeWidth: 2,
    });
    centerPosition(triangle);
    if (fabricRef.current) {
      fabricRef.current.add(triangle);
    }
  };

  const addText = () => {
    const text = new fabric.IText("Digite aqui", {
      fontSize: 24,
      fill: "black",
    });
    centerPosition(text);
    fabricRef.current.add(text);
  };

  const deleteSelected = () => {
    const active = fabricRef.current.getActiveObject();
    if (active) {
      fabricRef.current.remove(active);
      fabricRef.current.discardActiveObject();
      fabricRef.current.renderAll();
    }
  };

  const bringToFront = () => {
    if (!fabricRef.current) return;
    const obj = fabricRef.current.getActiveObject();
    if (obj) {
      obj.bringToFront();
      fabricRef.current.renderAll();
    }
  };

  const sendToBack = () => {
    const obj = fabricRef.current.getActiveObject();
    if (obj) {
      obj.sendToBack();
      fabricRef.current.renderAll();
    }
  };

  const clearCanvas = () => {
    if (confirm("Deseja limpar tudo?")) fabricRef.current.clear();
  };

  const saveProject = () => {
    const json = fabricRef.current.toJSON();
    localStorage.setItem("certificadoProjeto", JSON.stringify(json));
    alert("Projeto salvo!");
  };

  const loadProject = () => {
    const json = localStorage.getItem("certificadoProjeto");
    if (json) fabricRef.current.loadFromJSON(json, fabricRef.current.renderAll.bind(fabricRef.current));
  };

  const exportImage = async () => {
    const uri = await captureRef(canvasRef, { format: "png", quality: 1 });
    console.log("Imagem exportada em:", uri);
    alert("Imagem exportada! Veja o log para URI.");
  };

  return (
    <ScrollView style={{ flex: 1 }}>
      <ImageBackground
        source={{ uri: "https://i.imgur.com/4AiXzf8.jpeg" }}
        style={{ alignItems: "center", paddingVertical: 20 }}
      >
        <View
          style={{
            borderWidth: 1,
            borderColor: "#000",
            backgroundColor: "#fff",
          }}
        >
          <canvas
            id="certificateCanvas"
            ref={canvasRef}
            style={{
              width: canvasSize.width,
              height: canvasSize.height,
              border: "1px solid #ccc",
            }}
          />
        </View>

        {/* Botões */}
        <View style={{ flexDirection: "row", flexWrap: "wrap", marginTop: 20, gap: 10 }}>
          <Button title="Quadrado" onPress={addRectangle} />
          <Button title="Círculo" onPress={addCircle} />
          <Button title="Triângulo" onPress={addTriangle} />
          <Button title="Texto" onPress={addText} />
          <Button title="Excluir" onPress={deleteSelected} />
          <Button title="Trazer Frente" onPress={bringToFront} />
          <Button title="Enviar Fundo" onPress={sendToBack} />
          <Button title="Salvar" onPress={saveProject} />
          <Button title="Carregar" onPress={loadProject} />
          <Button title="Limpar" onPress={clearCanvas} />
          <Button title="Exportar" onPress={exportImage} />
        </View>
      </ImageBackground>
    </ScrollView>
  );
}
